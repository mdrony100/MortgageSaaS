<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The public demo form no longer asks for monthly volume. Keep the column
 * so earlier requests stay readable, and allow new requests to leave it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE demo_requests ALTER COLUMN monthly_volume DROP NOT NULL');
    }

    public function down(): void
    {
        DB::table('demo_requests')->whereNull('monthly_volume')->update(['monthly_volume' => '']);
        DB::statement('ALTER TABLE demo_requests ALTER COLUMN monthly_volume SET NOT NULL');
    }
};
