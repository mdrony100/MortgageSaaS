<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('platform_admin')->default(false);
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->integer('file_limit');
            $t->integer('member_limit');
            $t->bigInteger('storage_limit_bytes');
            $t->decimal('provisional_monthly_price', 10, 2);
            $t->timestamps();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('plan_id')->constrained();
            $t->jsonb('branding')->nullable();
            $t->jsonb('preferences')->nullable();
            $t->string('subscription_status')->default('trial');
            $t->timestamps();
        });
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->boolean('org_wide_access')->default(false);
            $t->timestamps();
            $t->unique(['organization_id', 'user_id']);
        });
        Schema::create('invitations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('organization_id')->constrained();
            $t->string('email');
            $t->string('role');
            $t->string('token_hash', 64)->unique();
            $t->foreignId('invited_by')->constrained('users');
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('borrowers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->timestamps();
            $t->unique(['id', 'organization_id']);
        });
        Schema::create('loan_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('borrower_id');
            $t->foreignId('created_by')->constrained('users');
            $t->string('name');
            $t->string('reference')->nullable();
            $t->string('status')->default('draft');
            $t->string('module')->default('bank');
            $t->boolean('is_demo')->default(false);
            $t->timestamp('archived_at')->nullable();
            $t->jsonb('draft_inputs')->nullable();
            $t->timestamps();
            $t->unique(['id', 'organization_id']);
            $t->foreign(['borrower_id', 'organization_id'])->references(['id', 'organization_id'])->on('borrowers');
        });
        Schema::create('file_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->foreignId('user_id')->constrained();
            $t->foreignId('shared_by')->constrained('users');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['organization_id', 'user_id'])->references(['organization_id', 'user_id'])->on('memberships');
            $t->unique(['loan_file_id', 'user_id']);
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->string('title');
            $t->string('category')->default('source');
            $t->timestamps();
            $t->unique(['id', 'organization_id']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
        });
        Schema::create('document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('document_id');
            $t->foreignId('uploaded_by')->constrained('users');
            $t->integer('version');
            $t->string('disk');
            $t->string('storage_key')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->bigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('validation_status')->default('accepted_sample');
            $t->string('scan_status')->default('not_configured');
            $t->string('status')->default('Uploaded — analysis integration pending.');
            $t->timestamps();
            $t->unique(['document_id', 'version']);
            $t->foreign(['document_id', 'organization_id'])->references(['id', 'organization_id'])->on('documents');
        });
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->string('visibility')->default('shared');
            $t->timestamps();
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('organization_id')->nullable()->constrained();
            $t->foreignId('loan_file_id')->nullable()->constrained();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('visibility')->default('internal');
            $t->jsonb('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('analysis_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->string('module');
            $t->string('stage');
            $t->string('status');
            $t->boolean('is_demo')->default(false);
            $t->string('idempotency_key')->unique();
            $t->integer('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['id', 'organization_id']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
        });
        Schema::create('rulesets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->string('name');
            $t->integer('version');
            $t->string('status')->default('fictional_draft');
            $t->jsonb('rules');
            $t->timestamps();
            $t->unique(['organization_id', 'name', 'version']);
        });
        Schema::create('analysis_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->uuid('analysis_run_id')->unique();
            $t->foreignId('ruleset_id')->nullable()->constrained();
            $t->jsonb('data');
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('statement_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('transfer_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('income_calculations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('nsf_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('sustainability_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('fraud_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('appraisals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('appraisal_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('leases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('dscr_scenarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('source_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('classification_suggestions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('reviewer_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
        Schema::create('report_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('analysis_run_id')->nullable();
            $t->string('external_key')->nullable();
            $t->jsonb('data')->nullable();
            $t->decimal('amount', 18, 2)->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
            $t->unique(['analysis_run_id', 'external_key']);
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['analysis_run_id', 'organization_id'])->references(['id', 'organization_id'])->on('analysis_runs');
        });
    }

    public function down(): void
    {
        foreach (['report_snapshots', 'reviewer_overrides', 'classification_suggestions', 'source_evidence', 'dscr_scenarios', 'leases', 'appraisal_observations', 'appraisals', 'fraud_findings', 'sustainability_metrics', 'nsf_events', 'income_calculations', 'transfer_matches', 'transactions', 'statement_periods', 'bank_accounts', 'analysis_snapshots', 'rulesets', 'analysis_runs', 'audit_events', 'notes', 'document_versions', 'documents', 'file_shares', 'loan_files', 'borrowers', 'invitations', 'memberships', 'organizations', 'plans'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['platform_admin', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
