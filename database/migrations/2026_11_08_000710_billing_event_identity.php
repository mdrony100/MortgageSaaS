<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX billing_external_event_identity ON billing_events (operation_key) WHERE operation_key LIKE 'event:%'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing_external_event_identity');
    }
};
