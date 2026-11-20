<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing organizations store the product wordmark in branding.product_name.
 * Replace the previous brand strings so customer workspaces show Non-QM Doc AI.
 * Custom names are left as entered.
 */
return new class extends Migration
{
    private const PREVIOUS = ['Non-QM Doc', 'MortgageLens', 'MortgageSaaS', 'nonqmdoc', 'NonQMDoc'];

    public function up(): void
    {
        $list = implode(', ', array_map(fn ($name) => "'".str_replace("'", "''", $name)."'", self::PREVIOUS));
        DB::statement("UPDATE organizations SET branding = jsonb_set(branding, '{product_name}', '\"Non-QM Doc AI\"'::jsonb) WHERE branding->>'product_name' IN ($list)");
    }

    public function down(): void
    {
        DB::statement("UPDATE organizations SET branding = jsonb_set(branding, '{product_name}', '\"Non-QM Doc\"'::jsonb) WHERE branding->>'product_name' = 'Non-QM Doc AI'");
    }
};
