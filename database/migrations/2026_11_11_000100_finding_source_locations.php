<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nullable columns only. PostgreSQL 10 adds these without a table rewrite. */
    public function up(): void
    {
        foreach (['bank_findings', 'review_records', 'fraud_findings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedSmallInteger('source_page')->nullable();
                $t->jsonb('source_bbox')->nullable();
                $t->boolean('bbox_verified')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['bank_findings', 'review_records', 'fraud_findings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['source_page', 'source_bbox', 'bbox_verified']);
            });
        }
    }
};
