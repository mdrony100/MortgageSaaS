<?php

use App\Appraisal\FictionalDocuments;
use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('mortgage:dev-account', function () {
    if (! app()->environment('local')) {
        $this->error('Development account generation is local-only.');

        return 1;
    }
    $password = Str::random(36).'9a';
    $email = 'review-'.Str::lower(Str::random(8)).'@example.test';
    $user = DB::transaction(function () use ($password, $email) {
        $plan = Plan::where('name', 'Starter')->firstOrFail();
        $u = User::create(['name' => 'Fictional Reviewer', 'email' => $email, 'password' => $password]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $org = Organization::create(['name' => 'Fictional Review Workspace', 'plan_id' => $plan->id]);
        Membership::create(['organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'owner', 'org_wide_access' => true]);
        $b = Borrower::create(['organization_id' => $org->id, 'name' => 'Fictional Demo Borrower']);
        foreach (['bank', 'appraisal', 'dscr'] as $module) {
            LoanFile::create(['organization_id' => $org->id, 'borrower_id' => $b->id, 'created_by' => $u->id, 'name' => 'Fictional '.strtoupper($module).' review', 'module' => $module, 'is_demo' => true]);
        }

        return $u;
    });
    $path = storage_path('app/dev-account-'.Str::uuid().'.json');
    file_put_contents($path, json_encode(['email' => $email, 'password' => $password, 'notice' => 'Local fictional account only. Delete this credential file after saving securely.'], JSON_PRETTY_PRINT));
    chmod($path, 0600);
    $this->info('Local fictional account created. Credentials are saved privately at: '.$path);
    $this->line('No analysis was run. Use real private upload processing; sample analysis is a separate explicitly fictional action.');
})->purpose('Generate a private local-only fictional review account; never an impersonation shortcut.');

// Operator-only: platform admin is never granted from the web UI. Safe in local and production.
Artisan::command('mortgage:platform-admin {email} {--revoke}', function (string $email) {
    $user = User::where('email', strtolower(trim($email)))->first();
    if (! $user) {
        $this->error('No user with that email address. The person must register (and verify their email) first.');

        return 1;
    }
    $grant = ! $this->option('revoke');
    if ($user->platform_admin === $grant) {
        $this->info($grant ? 'This user is already a platform admin.' : 'This user is not a platform admin.');

        return 0;
    }
    $user->forceFill(['platform_admin' => $grant])->save();
    Audit::record($grant ? 'platform.admin_granted' : 'platform.admin_revoked', null, null, ['target_user_id' => $user->id, 'via' => 'cli']);
    $this->info(($grant ? 'Granted' : 'Revoked').' platform admin for user #'.$user->id.'.');
    if ($grant && ! $user->hasVerifiedEmail()) {
        $this->warn('This user has not verified their email yet; the owner console stays closed until they do.');
    }
    if ($grant && config('platform.console_require_2fa') && ! $user->hasConfirmedTwoFactor()) {
        $this->warn('Two-factor sign-in is required for the owner console. They must turn it on in My account first.');
    }

    return 0;
})->purpose('Grant (or --revoke) MortgageLens platform admin / owner console access for an existing user.');

// Only transient working directories and encrypted replay cache expire. Original evidence,
// normalized ledgers, decisions and issued reports are never purged by this command.
Artisan::command('bank:purge-transient {--execute}', function () {
    if (! $this->option('execute') || ! config('review.retention.approved')) {
        $this->info('DRY RUN: transient bank cleanup requires approved retention policy and explicit --execute; no data deleted.');

        return;
    }
    $expired = DB::table('bank_provider_calls')->where('provider', 'openai')->where('updated_at', '<', now()->subDays(config('bank.cache_days')))->get();
    $count = 0;
    foreach ($expired as $call) {
        $data = json_decode($call->data, true);
        if (isset($data['encrypted_extraction'])) {
            unset($data['encrypted_extraction']);
            $data['extraction_cache_expired'] = true;
            DB::table('bank_provider_calls')->where('id', $call->id)->update(['data' => json_encode($data, JSON_THROW_ON_ERROR)]);
            $count++;
        }
    }
    $work = storage_path('app/bank-work');
    if (is_dir($work)) {
        foreach (new DirectoryIterator($work) as $item) {
            if ($item->isDir() && ! $item->isDot() && $item->getMTime() < time() - 86400) {
                File::deleteDirectory($item->getPathname());
            }
        }
    }
    $this->info('Expired transient replay caches: '.$count.'. Original evidence untouched.');
});
Schedule::command('bank:purge-transient')->daily()->withoutOverlapping();

Artisan::command('retention:review', function () {
    $this->line(json_encode(['mode' => 'dry_run_only', 'policy' => config('review.retention'), 'inventory' => ['originals' => DB::table('document_versions')->count(), 'reviews' => DB::table('review_runs')->count(), 'original_revisions' => DB::table('document_versions')->where('version', '>', 1)->count(), 'review_artifacts' => DB::table('review_records')->whereIn('kind', ['page', 'image'])->count(), 'issued_review_reports' => DB::table('review_records')->where('kind', 'report')->count(), 'stored_report_artifacts' => DB::table('review_records')->where('kind', 'report_artifact')->count(), 'review_database_records' => DB::table('review_records')->count()], 'provider_copies' => 'Operator/vendor agreement required', 'backups' => 'External backup inventory/restore acceptance required', 'deletion_executed' => false]));
});

Artisan::command('review:purge-replay {--execute}', function () {
    if (! $this->option('execute') || ! config('review.retention.approved')) {
        $this->info('DRY RUN: replay cleanup requires approved retention policy and explicit --execute; no data deleted.');

        return;
    }
    $count = 0;
    foreach (DB::table('review_calls')->where('data->status', 'completed')->get() as $row) {
        $d = json_decode($row->data, true);
        if (isset($d['cache']) && strtotime($d['cache_expires_at'] ?? '1970-01-01') < time()) {
            unset($d['cache']);
            DB::table('review_calls')->where('id', $row->id)->update(['data' => json_encode($d)]);
            $count++;
        }
    }$this->info('Expired encrypted replay caches: '.$count.'. Original evidence/reports untouched.');
});
Schedule::command('review:purge-replay')->daily();

Artisan::command('mortgage:fictional-review-documents', function () {
    $this->info('Private FICTIONAL labeled-layout PDFs generated at: '.app(FictionalDocuments::class)->generate());
    $this->line('Not supplied client templates or real financial documents. No analysis or external call performed.');
});
