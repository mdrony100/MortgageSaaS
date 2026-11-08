<?php

namespace App\Actions\Fortify;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $input['email'] = strtolower($input['email'] ?? '');
        Validator::make($input, ['name' => 'required|string|max:120', 'organization_name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:254', Rule::unique(User::class)], 'password' => $this->passwordRules()])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
            $plan = Plan::firstOrCreate(['name' => 'Starter'], ['file_limit' => 25, 'member_limit' => 5, 'storage_limit_bytes' => 1073741824, 'provisional_monthly_price' => '49.00']);
            $org = Organization::create(['name' => $input['organization_name'], 'plan_id' => $plan->id, 'branding' => ['product_name' => config('app.name'), 'accent' => '#0f766e'], 'preferences' => ['timezone' => 'America/New_York', 'currency' => 'USD']]);
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner', 'org_wide_access' => true]);
            Audit::record('organization.created', $org->id);

            return $user;
        });
    }
}
