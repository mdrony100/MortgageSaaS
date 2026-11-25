<?php

namespace App\Billing;

use App\Bank\ProcessingException;
use App\Models\Organization;
use App\Models\PageCreditAccount;
use App\Models\PageCreditEntry;
use App\Models\Plan;
use App\Services\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer-facing page credits. Separate from the OpenAI processing budget.
 * Balance never goes below zero. Unused monthly pages stay in the balance, and
 * a new monthly allocation is added when the period rolls.
 */
class PageCredits
{
    public const INSUFFICIENT = 'Your page credits are used up. Request more pages from Plan.';

    public const FILE_CAP = 'This file has more pages than your plan allows for one loan file. Request a plan change from Plan.';

    public const STARTING_PAGES = 500;

    public static function percentUsed(int $used, int $remaining): int
    {
        $total = $used + max(0, $remaining);
        if ($total <= 0) {
            return 100;
        }

        return (int) floor(($used / $total) * 100);
    }

    public static function warns(int $used, int $remaining): bool
    {
        return $remaining <= 0 || self::percentUsed($used, $remaining) >= 80;
    }

    /** @param  iterable<int, DocumentVersion>|iterable<DocumentVersion>  $versions */
    public function pagesForVersions(iterable $versions): int
    {
        $pages = 0;
        $counter = app(PdfPageCounter::class);
        foreach ($versions as $version) {
            $pages += $counter->countVersion($version);
        }

        return max(1, $pages);
    }

    public function provision(Organization $org, ?int $actorId = null, ?string $reason = null): PageCreditAccount
    {
        try {
            return DB::transaction(function () use ($org, $actorId, $reason) {
                $existing = PageCreditAccount::where('organization_id', $org->id)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
                $plan = Plan::findOrFail($org->plan_id);
                $account = PageCreditAccount::create([
                    'organization_id' => $org->id,
                    'balance' => 0,
                    'used_this_period' => 0,
                    'allocated_this_period' => 0,
                    'trial_granted' => false,
                    'period_starts_at' => now(),
                    'period_ends_at' => now()->copy()->addMonth(),
                ]);
                $this->grantOpening($account, $plan, $actorId, $reason);

                return $account->fresh();
            });
        } catch (UniqueConstraintViolationException) {
            return PageCreditAccount::where('organization_id', $org->id)->firstOrFail();
        }
    }

    public function summary(Organization $org): array
    {
        $account = $this->provision($org);
        $plan = Plan::findOrFail($org->plan_id);
        DB::transaction(function () use ($account, $plan) {
            $locked = PageCreditAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->rollPeriod($locked, $plan);
        });
        $account = $account->fresh();
        $plan = $plan->fresh();
        $used = (int) $account->used_this_period;
        $remaining = (int) $account->balance;
        $percent = self::percentUsed($used, $remaining);
        $warning = self::warns($used, $remaining);

        return [
            'plan' => $this->planPayload($plan),
            'balance' => $remaining,
            'used_this_period' => $used,
            'allocated_this_period' => (int) $account->allocated_this_period,
            'percent_used' => $percent,
            'warning' => $warning,
            'warning_message' => $this->warningMessage($used, $remaining),
            'period_starts_at' => $account->period_starts_at,
            'period_ends_at' => $account->period_ends_at,
            'offline_notice' => 'Payment is arranged offline. Send a request and the team will contact you. Nothing on this page charges a card.',
        ];
    }

    public function assertCanSpend(int $organizationId, int $pages, ?int $loanFileId = null): void
    {
        DB::transaction(function () use ($organizationId, $pages, $loanFileId) {
            $org = Organization::lockForUpdate()->findOrFail($organizationId);
            $account = $this->lockedAccount($org);
            $plan = Plan::findOrFail($org->plan_id);
            $this->rollPeriod($account, $plan);
            $this->guard($account->fresh(), $plan, $pages, $loanFileId);
        });
    }

    /** @param  array<string, mixed>  $context */
    public function consume(int $organizationId, int $pages, string $key, array $context, bool $processing = false): void
    {
        if ($pages < 1) {
            return;
        }
        try {
            DB::transaction(function () use ($organizationId, $pages, $key, $context) {
                $org = Organization::lockForUpdate()->findOrFail($organizationId);
                $account = $this->lockedAccount($org);
                $plan = Plan::findOrFail($org->plan_id);
                $this->rollPeriod($account, $plan);
                if (PageCreditEntry::where('organization_id', $organizationId)->where('idempotency_key', $key)->exists()) {
                    return;
                }
                $account = $account->fresh();
                $this->guard($account, $plan, $pages, $context['loan_file_id'] ?? null);
                $entry = $this->write($account, 'consumption', -$pages, (string) ($context['reason'] ?? 'PDF pages processed'), $context['actor_id'] ?? null, $key, $context);
                Audit::record('page_credits.consumed', $organizationId, $context['loan_file_id'] ?? null, ['pages' => $pages, 'balance' => $entry->balance_after, 'pipeline' => $context['pipeline'] ?? null], 'internal', $context['actor_id'] ?? null);
            });
        } catch (ValidationException $e) {
            if ($processing) {
                $message = collect($e->errors())->flatten()->first() ?: self::INSUFFICIENT;
                throw new ProcessingException((string) $message);
            }
            throw $e;
        }
    }

    public function adjust(Organization $org, int $pages, string $reason, int $actorId): PageCreditEntry
    {
        if ($pages === 0) {
            throw ValidationException::withMessages(['pages' => 'Enter a number of pages other than zero.']);
        }

        return DB::transaction(function () use ($org, $pages, $reason, $actorId) {
            $org = Organization::lockForUpdate()->findOrFail($org->id);
            $account = $this->lockedAccount($org);
            $plan = Plan::findOrFail($org->plan_id);
            $this->rollPeriod($account, $plan);
            $account = $account->fresh();
            if ($account->balance + $pages < 0) {
                throw ValidationException::withMessages(['pages' => 'That adjustment would take the balance below zero.']);
            }
            $entry = $this->write($account, 'adjustment', $pages, $reason, $actorId, null, []);
            Audit::record('page_credits.adjusted', $org->id, null, ['pages' => $pages, 'balance' => $entry->balance_after, 'reason' => $reason], 'internal', $actorId);

            return $entry;
        });
    }

    public function credit(Organization $org, int $pages, string $type, string $reason, ?int $actorId, ?string $key, ?int $paymentRequestId = null): PageCreditEntry
    {
        if ($pages < 1) {
            throw ValidationException::withMessages(['pages' => 'Page credits must be at least 1.']);
        }

        return DB::transaction(function () use ($org, $pages, $type, $reason, $actorId, $key, $paymentRequestId) {
            $org = Organization::lockForUpdate()->findOrFail($org->id);
            $account = $this->lockedAccount($org);
            $plan = Plan::findOrFail($org->plan_id);
            $this->rollPeriod($account, $plan);
            $account = $account->fresh();
            if ($key && ($existing = PageCreditEntry::where('organization_id', $org->id)->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            return $this->write($account, $type, $pages, $reason, $actorId, $key, ['payment_request_id' => $paymentRequestId]);
        });
    }

    /** Grant the gap up to the new plan's monthly quota. Does not remove pages already on the balance. */
    public function adoptPlan(Organization $org, ?int $actorId): void
    {
        DB::transaction(function () use ($org, $actorId) {
            $org = Organization::lockForUpdate()->findOrFail($org->id);
            $account = $this->lockedAccount($org);
            $plan = Plan::findOrFail($org->plan_id);
            $this->rollPeriod($account, $plan);
            $account = $account->fresh();
            $monthly = (int) $plan->monthly_page_quota;
            $gap = $monthly - (int) $account->allocated_this_period;
            if ($gap > 0) {
                $this->write($account, 'monthly_allocation', $gap, 'Monthly pages for the '.$plan->name.' plan', $actorId, null, []);
                $account->allocated_this_period = $monthly;
                $account->save();
            }
            $trial = (int) $plan->trial_page_allowance;
            if ($trial > 0 && ! $account->trial_granted) {
                $account = $account->fresh();
                $this->write($account, 'grant', $trial, 'Trial page allowance', $actorId, 'trial:'.$org->id, []);
                $account->trial_granted = true;
                $account->save();
            }
        });
    }

    public function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'summary' => $plan->summary,
            'is_active' => (bool) $plan->is_active,
            'listed_publicly' => (bool) $plan->listed_publicly,
            'display_monthly_price' => number_format((float) $plan->provisional_monthly_price, 2, '.', ''),
            'monthly_page_quota' => (int) $plan->monthly_page_quota,
            'trial_page_allowance' => (int) $plan->trial_page_allowance,
            'per_file_page_cap' => $plan->per_file_page_cap !== null ? (int) $plan->per_file_page_cap : null,
            'topup_packs' => $plan->packs(),
            'file_limit' => (int) $plan->file_limit,
            'member_limit' => (int) $plan->member_limit,
            'storage_limit_bytes' => (int) $plan->storage_limit_bytes,
        ];
    }

    private function grantOpening(PageCreditAccount $account, Plan $plan, ?int $actorId, ?string $reason): void
    {
        $monthly = (int) $plan->monthly_page_quota;
        $trial = (int) $plan->trial_page_allowance;
        if ($trial > 0 && $monthly === 0) {
            $this->write($account, 'grant', $trial, $reason ?: 'Trial page allowance', $actorId, 'opening:'.$account->organization_id, []);
            $account->trial_granted = true;
            $account->save();

            return;
        }
        $pages = $monthly > 0 ? $monthly : self::STARTING_PAGES;
        $this->write($account, 'monthly_allocation', $pages, $reason ?: 'Monthly page allocation', $actorId, 'opening:'.$account->organization_id, []);
        $account->allocated_this_period = $pages;
        $account->save();
    }

    private function lockedAccount(Organization $org): PageCreditAccount
    {
        $account = PageCreditAccount::where('organization_id', $org->id)->lockForUpdate()->first();
        if ($account) {
            return $account;
        }
        $this->provision($org);

        return PageCreditAccount::where('organization_id', $org->id)->lockForUpdate()->firstOrFail();
    }

    private function rollPeriod(PageCreditAccount $account, Plan $plan): void
    {
        if ($account->period_ends_at && $account->period_ends_at->isFuture()) {
            return;
        }
        $monthly = (int) $plan->monthly_page_quota;
        if ($monthly > 0) {
            $key = 'period:'.$account->organization_id.':'.now()->format('Y-m');
            $existing = PageCreditEntry::where('organization_id', $account->organization_id)->where('idempotency_key', $key)->exists();
            if (! $existing) {
                $account = $account->fresh();
                $this->write($account, 'monthly_allocation', $monthly, 'Monthly page allocation', null, $key, []);
                $account->allocated_this_period = $monthly;
                $account->used_this_period = 0;
            }
        }
        $account->period_starts_at = now();
        $account->period_ends_at = now()->copy()->addMonth();
        $account->save();
    }

    private function guard(PageCreditAccount $account, Plan $plan, int $pages, ?int $loanFileId): void
    {
        $cap = $plan->per_file_page_cap;
        if ($cap !== null) {
            $already = 0;
            if ($loanFileId) {
                $already = (int) PageCreditEntry::where('organization_id', $account->organization_id)->where('loan_file_id', $loanFileId)->where('type', 'consumption')->sum(DB::raw('ABS(pages)'));
            }
            if ($already + $pages > (int) $cap) {
                throw ValidationException::withMessages(['pages' => self::FILE_CAP]);
            }
        }
        if ($account->balance < $pages) {
            throw ValidationException::withMessages(['pages' => self::INSUFFICIENT]);
        }
    }

    /** @param  array<string, mixed>  $context */
    private function write(PageCreditAccount $account, string $type, int $pages, string $reason, ?int $actorId, ?string $key, array $context): PageCreditEntry
    {
        if ($key) {
            $existing = PageCreditEntry::where('organization_id', $account->organization_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
        }
        $next = (int) $account->balance + $pages;
        if ($next < 0) {
            throw ValidationException::withMessages(['pages' => self::INSUFFICIENT]);
        }
        $account->balance = $next;
        if ($type === 'consumption') {
            $account->used_this_period = (int) $account->used_this_period + abs($pages);
        }
        $account->save();

        return PageCreditEntry::create([
            'organization_id' => $account->organization_id,
            'type' => $type,
            'pages' => $pages,
            'balance_after' => $next,
            'reason' => mb_substr($reason, 0, 500),
            'actor_id' => $actorId,
            'payment_request_id' => $context['payment_request_id'] ?? null,
            'loan_file_id' => $context['loan_file_id'] ?? null,
            'document_version_id' => $context['document_version_id'] ?? null,
            'pipeline' => $context['pipeline'] ?? null,
            'idempotency_key' => $key,
            'metadata' => isset($context['metadata']) ? $context['metadata'] : null,
        ]);
    }

    private function warningMessage(int $used, int $remaining): ?string
    {
        if (! self::warns($used, $remaining)) {
            return null;
        }
        if ($remaining <= 0) {
            return self::INSUFFICIENT;
        }

        return 'You have used '.self::percentUsed($used, $remaining).'% of your page credits. Request more pages from Plan before processing stops.';
    }
}
