<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_bank_run_inputs() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.options IS DISTINCT FROM OLD.options OR NEW.input_hash IS DISTINCT FROM OLD.input_hash OR NEW.rule_id IS DISTINCT FROM OLD.rule_id OR NEW.requested_by <> OLD.requested_by OR NEW.idempotency_key <> OLD.idempotency_key OR NEW.organization_id <> OLD.organization_id OR NEW.loan_file_id <> OLD.loan_file_id THEN
 RAISE EXCEPTION 'Pinned bank run inputs cannot change'; END IF; RETURN NEW;
END $$;
SQL);
    }

    public function down(): void {}
};
