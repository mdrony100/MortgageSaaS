<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Starter', 25, 5, 1073741824, '49.00'], ['Team', 250, 25, 10737418240, '149.00'], ['Enterprise', 2500, 100, 107374182400, '499.00']] as [$name,$files,$members,$storage,$price]) {
            Plan::updateOrCreate(['name' => $name], ['file_limit' => $files, 'member_limit' => $members, 'storage_limit_bytes' => $storage, 'provisional_monthly_price' => $price]);
        }
    }
}
