<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->foreignId('requested_by')->constrained('users');
            $t->uuid('parent_id')->nullable();
            $t->string('idempotency_key');
            $t->jsonb('inputs');
            $t->jsonb('context');
            $t->jsonb('checklist');
            $t->string('status')->default('Queued');
            $t->integer('attempts')->default(0);
            $t->boolean('cancel_requested')->default(false);
            $t->string('error')->nullable();
            $t->jsonb('metrics')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'loan_file_id', 'idempotency_key']);
            $t->unique(['id', 'organization_id', 'loan_file_id']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
        });
        Schema::table('review_runs', function (Blueprint $t) {
            $t->foreign(['parent_id', 'organization_id', 'loan_file_id'])->references(['id', 'organization_id', 'loan_file_id'])->on('review_runs');
        });
        foreach (['review_records', 'review_calls'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->uuid('run_id');
                $t->foreignId('organization_id');
                $t->foreignId('loan_file_id');
                $t->string('kind');
                $t->string('record_key');
                $t->jsonb('data');
                $t->timestamps();
                $t->unique(['run_id', 'kind', 'record_key']);
                $t->foreign(['run_id', 'organization_id', 'loan_file_id'])->references(['id', 'organization_id', 'loan_file_id'])->on('review_runs');
            });
        }
        Schema::create('guideline_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('status');
            $t->jsonb('data');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('billing_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->unique()->constrained();
            $t->string('provider');
            $t->uuid('provider_reference')->unique();
            $t->string('status')->default('unconfigured');
            $t->jsonb('data');
            $t->timestamps();
        });
        foreach (['billing_events', 'usage_operations'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->string('operation_key');
                $t->string('status');
                $t->jsonb('data');
                $t->timestamps();
                $t->unique(['organization_id', 'operation_key']);
            });
        }
        DB::unprepared('CREATE TRIGGER review_records_immutable BEFORE UPDATE OR DELETE ON review_records FOR EACH ROW EXECUTE FUNCTION mortgage_immutable(); CREATE TRIGGER guideline_versions_immutable BEFORE UPDATE OR DELETE ON guideline_versions FOR EACH ROW EXECUTE FUNCTION mortgage_immutable(); CREATE TRIGGER billing_events_immutable BEFORE UPDATE OR DELETE ON billing_events FOR EACH ROW EXECUTE FUNCTION mortgage_immutable();');
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_review_pins() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.inputs IS DISTINCT FROM OLD.inputs OR NEW.context IS DISTINCT FROM OLD.context OR NEW.checklist IS DISTINCT FROM OLD.checklist OR NEW.parent_id IS DISTINCT FROM OLD.parent_id OR NEW.requested_by<>OLD.requested_by OR NEW.organization_id<>OLD.organization_id OR NEW.loan_file_id<>OLD.loan_file_id OR NEW.idempotency_key<>OLD.idempotency_key THEN RAISE EXCEPTION 'Review pins immutable'; END IF; RETURN NEW; END \$\$; CREATE TRIGGER review_pins BEFORE UPDATE ON review_runs FOR EACH ROW EXECUTE FUNCTION protect_review_pins();");
    }

    public function down(): void
    {
        foreach (['usage_operations', 'billing_events', 'billing_subscriptions', 'guideline_versions', 'review_calls', 'review_records', 'review_runs'] as $t) {
            Schema::dropIfExists($t);
        }DB::unprepared('DROP FUNCTION IF EXISTS protect_review_pins()');
    }
};
