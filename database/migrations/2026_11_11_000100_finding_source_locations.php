<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive location columns for issue review. Nullable, no defaults that rewrite rows,
 * and no foreign keys, so PostgreSQL 10 can apply this without rewriting evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bank_findings', 'review_records', 'fraud_findings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('source_page')->nullable();
                $t->unsignedBigInteger('source_document_version_id')->nullable();
                $t->decimal('bbox_x', 10, 6)->nullable();
                $t->decimal('bbox_y', 10, 6)->nullable();
                $t->decimal('bbox_w', 10, 6)->nullable();
                $t->decimal('bbox_h', 10, 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['fraud_findings', 'review_records', 'bank_findings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['source_page', 'source_document_version_id', 'bbox_x', 'bbox_y', 'bbox_w', 'bbox_h']);
            });
        }
    }
};
