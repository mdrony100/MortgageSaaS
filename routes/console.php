<?php

use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
    $this->line('No analysis was run. Use the separate Load sample analysis action.');
})->purpose('Generate a private local-only fictional review account; never an impersonation shortcut.');
