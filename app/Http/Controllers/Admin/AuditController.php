<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Read-only platform-level audit events. File-level (loan file) events are excluded so the
 * console never surfaces document or review activity details.
 */
class AuditController extends Controller
{
    public const CATEGORIES = [
        'admin' => ['Owner console actions', ['admin.%']],
        'auth' => ['Sign-ins and account security', ['auth.%']],
        'impersonation' => ['Support sessions', ['impersonation.%', 'platform_support.%']],
        'inquiry' => ['Website inquiries', ['website_inquiry.%']],
        'requests' => ['Customer requests', ['plan_change.%', 'organization.created']],
        'cli' => ['Operator commands', ['platform.%']],
    ];

    public static function query(array $f)
    {
        $cats = ! empty($f['category']) ? [self::CATEGORIES[$f['category']]] : array_values(self::CATEGORIES);
        $patterns = array_merge(...array_map(fn ($c) => $c[1], $cats));
        $q = AuditEvent::query()->from('audit_events as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')->leftJoin('organizations as o', 'o.id', '=', 'a.organization_id')
            ->whereNull('a.loan_file_id')
            ->where(fn ($w) => collect($patterns)->each(fn ($p) => $w->orWhere('a.action', 'like', $p)))
            ->orderByDesc('a.id')
            ->select(['a.id', 'a.action', 'a.actor_id', 'u.name as actor_name', 'u.email as actor_email', 'a.organization_id', 'o.name as organization', 'a.metadata', 'a.created_at']);
        if (! empty($f['actor'])) {
            $term = '%'.addcslashes($f['actor'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('u.email', 'ilike', $term)->orWhere('u.name', 'ilike', $term));
        }
        if (! empty($f['action'])) {
            $q->where('a.action', 'ilike', '%'.addcslashes($f['action'], '%_\\').'%');
        }
        if (! empty($f['from'])) {
            $q->where('a.created_at', '>=', Carbon::parse($f['from'])->startOfDay());
        }
        if (! empty($f['to'])) {
            $q->where('a.created_at', '<=', Carbon::parse($f['to'])->endOfDay());
        }

        return $q;
    }

    public static function rules(): array
    {
        return ['category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))], 'actor' => 'nullable|string|max:120', 'action' => 'nullable|string|max:80', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1'];
    }

    public function index(Request $r)
    {
        $f = $r->validate(self::rules());

        return ['events' => self::query($f)->paginate(30), 'categories' => collect(self::CATEGORIES)->map(fn ($c) => $c[0])];
    }
}
