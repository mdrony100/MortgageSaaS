<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new LogicException('MortgageLens requires PostgreSQL.');
        } DB::unprepared("CREATE OR REPLACE FUNCTION mortgage_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Evidence and audit rows are append-only'; END; $$;");
        foreach (['document_versions', 'audit_events', 'source_evidence', 'classification_suggestions', 'reviewer_overrides', 'analysis_snapshots', 'report_snapshots'] as $table) {
            DB::unprepared("CREATE TRIGGER immutable_{$table} BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE PROCEDURE mortgage_immutable()");
        }
    }

    public function down(): void
    {
        foreach (['document_versions', 'audit_events', 'source_evidence', 'classification_suggestions', 'reviewer_overrides', 'analysis_snapshots', 'report_snapshots'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS immutable_{$table} ON {$table}");
        } DB::unprepared('DROP FUNCTION IF EXISTS mortgage_immutable()');
    }
};
