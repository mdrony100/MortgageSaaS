<?php

use App\Http\Controllers\Admin\ApprovalController as AdminApprovals;
use App\Http\Controllers\Admin\AuditController as AdminAudit;
use App\Http\Controllers\Admin\ConsoleController as Console;
use App\Http\Controllers\Admin\OrganizationAdminController as AdminOrgs;
use App\Http\Controllers\Admin\ReportController as AdminReports;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use App\Http\Controllers\Admin\UserAdminController as AdminUsers;
use App\Http\Controllers\AnalysisController as A;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DocumentController as D;
use App\Http\Controllers\FileController as F;
use App\Http\Controllers\OrganizationController as O;
use App\Http\Controllers\PlanRequestController;
use App\Http\Controllers\PublicSiteController as Site;
use App\Http\Controllers\WebsiteInquiryController as Inquiries;
use App\Http\Middleware\SerializeBankReview;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public marketing website (server-rendered Blade, separate from the dashboard SPA).
Route::get('/', [Site::class, 'page'])->name('site.home');
Route::get('/features', [Site::class, 'page'])->name('site.features');
Route::get('/how-it-works', [Site::class, 'page'])->name('site.how');
Route::get('/pricing', [Site::class, 'page'])->name('site.pricing');
Route::get('/security', [Site::class, 'page'])->name('site.security');
Route::get('/faq', [Site::class, 'page'])->name('site.faq');
Route::get('/contact', [Site::class, 'page'])->name('site.contact');
Route::post('/contact', [Site::class, 'submit'])->middleware('throttle:website-inquiry')->name('site.contact.submit');
Route::get('/privacy', [Site::class, 'legal'])->name('site.privacy');
Route::get('/terms', [Site::class, 'legal'])->name('site.terms');
Route::get('/sitemap.xml', [Site::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [Site::class, 'robots'])->name('seo.robots');

Route::get('/api/me', function (Request $r) {
    $imp = Impersonation::current($r);

    return ['user' => $r->user(), 'memberships' => $r->user()->memberships()->with('organization.plan')->get(), 'impersonation' => $imp ? ['admin_name' => User::find($imp['admin_id'])?->name, 'expires_at' => date(DATE_ATOM, (int) $imp['expires_at'])] : null];
})->middleware('auth:sanctum');
Route::post('/api/impersonation/stop', function (Request $r) {
    abort_unless(Impersonation::current($r), 409, 'No support session is active.');
    $restored = Impersonation::stop($r, 'returned');

    return ['redirect' => $restored ? '/admin' : '/login'];
})->middleware('auth:sanctum');

// Owner console (platform staff only). Old in-workspace URLs redirect here.
Route::redirect('/platform', '/admin');
Route::get('/platform/inquiries', fn (Request $r) => redirect('/admin/inquiries'.($r->filled('id') ? '?id='.(int) $r->query('id') : '')));
Route::middleware(['auth:sanctum', 'owner.console:api', 'throttle:owner-console'])->prefix('api/admin')->group(function () {
    Route::get('me', [Console::class, 'me']);
    Route::get('overview', [Console::class, 'overview']);
    Route::get('analytics', [Console::class, 'analytics']);
    Route::get('revenue', [Console::class, 'revenue']);
    Route::get('plans', [Console::class, 'plans']);
    Route::get('system', [Console::class, 'system']);
    Route::get('settings', [AdminSettings::class, 'show']);
    Route::get('organizations', [AdminOrgs::class, 'index']);
    Route::get('organizations/{id}', [AdminOrgs::class, 'show'])->whereNumber('id');
    Route::get('users', [AdminUsers::class, 'index']);
    Route::get('impersonations', [AdminUsers::class, 'impersonations']);
    Route::get('approvals', [AdminApprovals::class, 'index']);
    Route::get('audit', [AdminAudit::class, 'index']);
    Route::get('reports', [AdminReports::class, 'index']);
    Route::get('reports/{type}', [AdminReports::class, 'export']);
    Route::get('inquiries', [Inquiries::class, 'index']);
    Route::get('inquiries/{id}', [Inquiries::class, 'show'])->whereNumber('id');
    Route::middleware('throttle:owner-actions')->group(function () {
        Route::post('organizations/{id}/actions', [AdminOrgs::class, 'action'])->whereNumber('id');
        Route::post('organizations/{id}/notes', [AdminOrgs::class, 'note'])->whereNumber('id');
        Route::post('users/{id}/actions', [AdminUsers::class, 'action'])->whereNumber('id');
        Route::post('approvals/{id}/decision', [AdminApprovals::class, 'decide'])->whereNumber('id');
        Route::patch('inquiries/{id}', [Inquiries::class, 'update'])->whereNumber('id');
        Route::post('inquiries/{id}/notes', [Inquiries::class, 'note'])->whereNumber('id');
        Route::post('inquiries/{id}/convert', [Inquiries::class, 'convert'])->whereNumber('id');
        Route::put('settings', [AdminSettings::class, 'update']);
        Route::post('settings/test', [AdminSettings::class, 'test']);
        Route::post('settings/test-email', [AdminSettings::class, 'testEmail']);
    });
    Route::post('impersonation', [AdminUsers::class, 'impersonate'])->middleware('throttle:impersonation');
});
Route::get('/admin/{path?}', [Console::class, 'shell'])->where('path', '.*')->middleware('owner.console:web')->name('admin.console');
Route::middleware(['auth:sanctum', 'verified', 'org.active', 'throttle:workspace'])->prefix('api')->group(function () {
    Route::get('organizations', [O::class, 'index']);
    Route::get('organizations/{organization}', [O::class, 'show']);
    Route::get('organizations/{organization}/audit', [O::class, 'audit']);
    Route::get('organizations/{organization}/usage', [O::class, 'usage']);
    Route::patch('organizations/{organization}', [O::class, 'update']);
    Route::post('organizations/{organization}/invitations', [O::class, 'invite']);
    Route::delete('organizations/{organization}/invitations/{invitation}', [O::class, 'revokeInvite']);
    Route::post('invitations/accept', [O::class, 'accept']);
    Route::patch('organizations/{organization}/members/{membership}', [O::class, 'member']);
    Route::delete('organizations/{organization}/members/{membership}', [O::class, 'removeMember']);
    Route::get('plans', [O::class, 'plans']);
    Route::get('organizations/{organization}/plan-requests', [PlanRequestController::class, 'index']);
    Route::post('organizations/{organization}/plan-requests', [PlanRequestController::class, 'store'])->middleware('throttle:owner-actions');
    Route::get('organizations/{organization}/borrowers', [F::class, 'borrowers']);
    Route::post('organizations/{organization}/borrowers', [F::class, 'borrower']);
    Route::patch('organizations/{organization}/borrowers/{borrower}', [F::class, 'updateBorrower']);
    Route::get('files', [F::class, 'index']);
    Route::post('organizations/{organization}/files', [F::class, 'store']);
    Route::get('files/{file}', [F::class, 'show']);
    Route::patch('files/{file}', [F::class, 'update']);
    Route::get('files/{file}/share-candidates', [F::class, 'shareCandidates']);
    Route::post('files/{file}/shares', [F::class, 'share']);
    Route::delete('files/{file}/shares/{share}', [F::class, 'revoke']);
    Route::post('files/{file}/notes', [F::class, 'note']);
    Route::get('files/{file}/activity', [F::class, 'activity']);
    Route::post('files/{file}/documents', [D::class, 'store'])->middleware('throttle:uploads');
    Route::post('documents/{document}/versions', [D::class, 'replace'])->middleware('throttle:uploads');
    Route::get('document-versions/{version}/content', [D::class, 'content']);
    Route::post('files/{file}/sample-analysis', [A::class, 'load']);
    Route::get('files/{file}/analysis', [A::class, 'show']);
    Route::get('files/{file}/analysis/restricted/{section}', [A::class, 'restricted']);
    Route::post('files/{file}/overrides', [A::class, 'override']);
    Route::get('files/{file}/review-history', [A::class, 'history']);
    Route::get('files/{file}/reports/{module}', [A::class, 'report']);
    Route::get('files/{file}/export', [A::class, 'export']);
    Route::get('rulesets', [A::class, 'rules']);
    $a = AppraisalController::class;
    Route::get('files/{file}/appraisal', [$a, 'show']);
    Route::post('files/{file}/appraisal/runs', [$a, 'start']);
    Route::post('files/{file}/appraisal/guidelines', [$a, 'guideline']);
    Route::post('files/{file}/appraisal/runs/{runId}/{action}', [$a, 'action']);
    Route::get('files/{file}/appraisal/runs/{runId}/artifacts/{recordId}', [$a, 'artifact']);
    Route::get('files/{file}/appraisal/runs/{runId}/reports/{recordId}/{format}', [$a, 'download']);
    Route::get('organizations/{organization}/billing', [BillingController::class, 'show']);
    Route::post('organizations/{organization}/billing', [BillingController::class, 'change']);
    $b = BankController::class;
    Route::get('files/{file}/bank', [$b, 'show']);
    Route::post('files/{file}/bank/rules', [$b, 'rule']);
    Route::post('files/{file}/bank/runs', [$b, 'start']);
    Route::post('files/{file}/bank/messages', [$b, 'message']);
    Route::post('files/{file}/bank/runs/{runId}/control/{action}', [$b, 'control'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/reviews', [$b, 'review'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/relationships', [$b, 'relationship'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/transfers/{matchId}', [$b, 'transfer'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/findings/{findingId}', [$b, 'finding'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/calculate', [$b, 'calculate'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/conditions', [$b, 'condition'])->middleware(SerializeBankReview::class);
    Route::post('files/{file}/bank/runs/{runId}/reports', [$b, 'issue'])->middleware(SerializeBankReview::class);
    Route::get('files/{file}/bank/runs/{runId}/reports/{reportId}/{format}', [$b, 'download']);
});
Route::post('/api/billing/test-webhook', [BillingController::class, 'webhook'])->withoutMiddleware(ValidateCsrfToken::class)->middleware('throttle:workspace');
// Fallback when a host still forwards icon requests to index.php. public/.htaccess
// serves these files directly. Never let the SPA catch-all answer /favicon.ico with HTML.
Route::get('/{asset}', function (string $asset) {
    $types = [
        'favicon.ico' => 'image/x-icon',
        'favicon.svg' => 'image/svg+xml',
        'favicon-32x32.png' => 'image/png',
        'favicon-16x16.png' => 'image/png',
        'apple-touch-icon.png' => 'image/png',
        'site.webmanifest' => 'application/manifest+json',
    ];
    $path = public_path($asset);
    abort_unless(isset($types[$asset]) && is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => $types[$asset],
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('asset', 'favicon\.ico|favicon\.svg|favicon-16x16\.png|favicon-32x32\.png|apple-touch-icon\.png|site\.webmanifest');
Route::get('/{path?}', fn () => view('app'))->where('path', '^(?!api/|sanctum/|horizon|admin(/|$)|(?:favicon(?:\.ico|\.svg|-16x16\.png|-32x32\.png)|apple-touch-icon\.png|site\.webmanifest)$).*$')->name('spa');
