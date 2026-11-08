<?php

use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
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
    $this->line('No analysis was run. Use the separate Load sample analysis action.');
})->purpose('Generate a private local-only fictional review account; never an impersonation shortcut.');

// Only transient working directories and encrypted replay cache expire. Original evidence,
// normalized ledgers, decisions and issued reports are never purged by this command.
Artisan::command('bank:purge-transient', function () {
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
