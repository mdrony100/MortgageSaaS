<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pack = [['pages' => 500, 'price' => '79.00', 'label' => '500 pages']];
        // Prices are placeholders. Edit them in the owner console. They are shown on the
        // pricing page and on payment requests; nothing is charged automatically.
        foreach ([
            ['Starter', 25, 5, 1073741824, '49.00', true, true, 500, 0, null, 'For a small team getting started with document review.', $pack],
            ['Team', 250, 25, 10737418240, '149.00', true, false, 2000, 0, null, 'For a growing processing team.', $pack],
            ['Enterprise', 2500, 100, 107374182400, '499.00', true, false, 10000, 0, null, 'For a high-volume review desk.', $pack],
            ['Trial', 5, 2, 1073741824, '0.00', true, true, 0, 50, null, '50 PDF pages to try the workspace. Placeholder price.', []],
            ['Pro', 100, 15, 10737418240, '199.00', true, true, 2000, 0, null, '2,000 PDF pages a month for an active review team. Placeholder price.', $pack],
            ['Business', 1000, 50, 107374182400, '499.00', true, true, 10000, 0, null, '10,000 PDF pages a month. Placeholder price.', [['pages' => 500, 'price' => '79.00', 'label' => '500 pages'], ['pages' => 2000, 'price' => '249.00', 'label' => '2,000 pages']]],
        ] as [$name, $files, $members, $storage, $price, $active, $listed, $quota, $trial, $cap, $summary, $packs]) {
            Plan::updateOrCreate(['name' => $name], [
                'file_limit' => $files,
                'member_limit' => $members,
                'storage_limit_bytes' => $storage,
                'provisional_monthly_price' => $price,
                'is_active' => $active,
                'listed_publicly' => $listed,
                'monthly_page_quota' => $quota,
                'trial_page_allowance' => $trial,
                'per_file_page_cap' => $cap,
                'summary' => $summary,
                'topup_packs' => $packs,
            ]);
        }
    }
}
