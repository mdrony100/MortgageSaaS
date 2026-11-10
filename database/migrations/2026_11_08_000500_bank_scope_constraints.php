<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_bank_document_scope() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS (SELECT 1 FROM document_versions v JOIN documents d ON d.id=v.document_id WHERE v.id=NEW.document_version_id AND v.organization_id=NEW.organization_id AND d.organization_id=NEW.organization_id AND d.loan_file_id=NEW.loan_file_id) THEN RAISE EXCEPTION 'Original document is outside bank run file scope'; END IF; RETURN NEW;
END $$;
CREATE TRIGGER bank_input_document_scope BEFORE INSERT ON bank_inputs FOR EACH ROW EXECUTE PROCEDURE protect_bank_document_scope();
CREATE TRIGGER bank_transaction_document_scope BEFORE INSERT ON bank_transactions FOR EACH ROW EXECUTE PROCEDURE protect_bank_document_scope();
ALTER TABLE bank_transactions ADD CONSTRAINT bank_credit_nonnegative CHECK (credit_minor IS NULL OR credit_minor >= 0);
ALTER TABLE bank_transactions ADD CONSTRAINT bank_debit_nonnegative CHECK (debit_minor IS NULL OR debit_minor >= 0);
ALTER TABLE bank_transactions ADD CONSTRAINT bank_single_direction CHECK (NOT (credit_minor > 0 AND debit_minor > 0));
CREATE TRIGGER bank_runs_no_delete BEFORE DELETE ON bank_runs FOR EACH ROW EXECUTE PROCEDURE mortgage_immutable();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS bank_input_document_scope ON bank_inputs; DROP TRIGGER IF EXISTS bank_transaction_document_scope ON bank_transactions; DROP FUNCTION IF EXISTS protect_bank_document_scope(); ALTER TABLE bank_transactions DROP CONSTRAINT IF EXISTS bank_credit_nonnegative; ALTER TABLE bank_transactions DROP CONSTRAINT IF EXISTS bank_debit_nonnegative; ALTER TABLE bank_transactions DROP CONSTRAINT IF EXISTS bank_single_direction; DROP TRIGGER IF EXISTS bank_runs_no_delete ON bank_runs;');
    }
};
