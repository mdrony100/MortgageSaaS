<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', fn (Blueprint $t) => $t->unique(['id', 'organization_id']));
        Schema::create('bank_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->unsignedInteger('version');
            $t->string('status');
            $t->jsonb('policy');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['organization_id', 'name', 'version']);
            $t->unique(['id', 'organization_id']);
        });
        Schema::create('bank_limits', function (Blueprint $t) {
            $t->foreignId('organization_id')->primary()->constrained();
            $t->bigInteger('limit_micro_usd')->default(0);
            $t->bigInteger('reserved_micro_usd')->default(0);
            $t->bigInteger('per_run_limit_micro_usd')->default(0);
            $t->timestamps();
        });
        Schema::create('bank_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('rule_id')->nullable();
            $t->string('idempotency_key')->unique();
            $t->string('status')->default('Queued');
            $t->string('stage')->default('Queued');
            $t->unsignedInteger('attempts')->default(0);
            $t->jsonb('options');
            $t->string('input_hash', 64);
            $t->boolean('cancel_requested')->default(false);
            $t->string('error_code')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['id', 'organization_id', 'loan_file_id']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['rule_id', 'organization_id'])->references(['id', 'organization_id'])->on('bank_rules');
        });
        Schema::create('bank_inputs', function (Blueprint $t) {
            $t->id();
            $this->scope($t);
            $t->foreignId('document_version_id');
            $t->string('sha256', 64);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['run_id', 'document_version_id']);
            $t->unique(['id', 'run_id']);
            $t->foreign(['document_version_id', 'organization_id'])->references(['id', 'organization_id'])->on('document_versions');
        });
        Schema::create('bank_scans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('document_version_id');
            $t->string('status');
            $t->string('adapter');
            $t->string('sha256', 64);
            $t->jsonb('data');
            $t->timestamp('created_at')->useCurrent();
            $t->foreign(['document_version_id', 'organization_id'])->references(['id', 'organization_id'])->on('document_versions');
        });
        foreach (['bank_pages', 'bank_statements', 'bank_reviews', 'bank_relationships', 'bank_transfer_matches', 'bank_transfer_reviews', 'bank_findings', 'bank_finding_reviews', 'bank_conditions', 'bank_messages', 'bank_calculations', 'bank_reports'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $this->scope($t);
                $t->string('record_key');
                $t->jsonb('data');
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['run_id', 'record_key']);
                $t->unique(['id', 'run_id']);
            });
        }
        Schema::create('bank_transactions', function (Blueprint $t) {
            $t->id();
            $this->scope($t);
            $t->foreignId('statement_id');
            $t->foreignId('document_version_id');
            $t->string('provenance_key', 64);
            $t->string('account_key', 64);
            $t->date('posting_date')->nullable();
            $t->bigInteger('credit_minor')->nullable();
            $t->bigInteger('debit_minor')->nullable();
            $t->bigInteger('balance_minor')->nullable();
            $t->jsonb('data');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['run_id', 'provenance_key']);
            $t->unique(['id', 'run_id']);
            $t->foreign(['statement_id', 'run_id'])->references(['id', 'run_id'])->on('bank_statements');
            $t->foreign(['document_version_id', 'organization_id'])->references(['id', 'organization_id'])->on('document_versions');
        });
        Schema::create('bank_provider_calls', function (Blueprint $t) {
            $t->id();
            $this->scope($t);
            $t->string('call_key', 64);
            $t->string('provider');
            $t->string('version');
            $t->string('status');
            $t->bigInteger('reserved_micro_usd');
            $t->jsonb('data');
            $t->timestamps();
            $t->unique(['organization_id', 'call_key']);
        });
        Schema::create('bank_vendor_books', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('book_uuid')->unique();
            $t->timestamps();
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->unique(['organization_id', 'loan_file_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['bank_rules', 'bank_inputs', 'bank_scans', 'bank_pages', 'bank_statements', 'bank_transactions', 'bank_reviews', 'bank_relationships', 'bank_transfer_matches', 'bank_transfer_reviews', 'bank_findings', 'bank_finding_reviews', 'bank_conditions', 'bank_messages', 'bank_calculations', 'bank_reports'] as $name) {
                DB::unprepared("CREATE TRIGGER {$name}_immutable BEFORE UPDATE OR DELETE ON {$name} FOR EACH ROW EXECUTE FUNCTION mortgage_immutable()");
            }
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_bank_run_inputs() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.options IS DISTINCT FROM OLD.options OR NEW.input_hash IS DISTINCT FROM OLD.input_hash OR NEW.rule_id IS DISTINCT FROM OLD.rule_id OR NEW.requested_by <> OLD.requested_by OR NEW.idempotency_key <> OLD.idempotency_key OR NEW.organization_id <> OLD.organization_id OR NEW.loan_file_id <> OLD.loan_file_id THEN
 RAISE EXCEPTION 'Pinned bank run inputs cannot change'; END IF; RETURN NEW;
END $$;
CREATE TRIGGER bank_run_pins BEFORE UPDATE ON bank_runs FOR EACH ROW EXECUTE FUNCTION protect_bank_run_inputs();

SQL);
        }
    }

    private function scope(Blueprint $t): void
    {
        $t->uuid('run_id');
        $t->foreignId('organization_id');
        $t->foreignId('loan_file_id');
        $t->foreign(['run_id', 'organization_id', 'loan_file_id'])->references(['id', 'organization_id', 'loan_file_id'])->on('bank_runs');
    }

    public function down(): void
    {
        foreach (['bank_vendor_books', 'bank_provider_calls', 'bank_transactions', 'bank_reports', 'bank_calculations', 'bank_messages', 'bank_conditions', 'bank_finding_reviews', 'bank_findings', 'bank_transfer_reviews', 'bank_transfer_matches', 'bank_relationships', 'bank_reviews', 'bank_statements', 'bank_pages', 'bank_scans', 'bank_inputs', 'bank_runs', 'bank_limits', 'bank_rules'] as $name) {
            Schema::dropIfExists($name);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS protect_bank_run_inputs()');
        }
        Schema::table('document_versions', fn (Blueprint $t) => $t->dropUnique(['id', 'organization_id']));
    }
};
