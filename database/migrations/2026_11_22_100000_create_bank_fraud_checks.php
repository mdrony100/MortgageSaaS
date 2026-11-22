<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_fraud_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('loan_file_id');
            $t->uuid('run_id');
            $t->foreignId('document_version_id');
            $t->string('status', 32);
            $t->string('risk_level', 16)->nullable();
            $t->unsignedSmallInteger('score')->nullable();
            $t->text('summary')->nullable();
            $t->jsonb('findings')->nullable();
            $t->jsonb('signals')->nullable();
            $t->string('model', 80)->nullable();
            $t->string('error_code', 400)->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamps();
            $t->foreign(['loan_file_id', 'organization_id'])->references(['id', 'organization_id'])->on('loan_files');
            $t->foreign(['document_version_id', 'organization_id'])->references(['id', 'organization_id'])->on('document_versions');
            $t->foreign(['run_id', 'organization_id', 'loan_file_id'])->references(['id', 'organization_id', 'loan_file_id'])->on('bank_runs');
            $t->index(['run_id', 'document_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_fraud_checks');
    }
};
