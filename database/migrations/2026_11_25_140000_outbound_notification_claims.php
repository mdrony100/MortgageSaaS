<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which low-credit, trial, and processing notices already went out.
 * Additive only: nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_notification_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_key', 191)->unique();
            $table->string('kind', 80);
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->timestamp('claimed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_notification_claims');
    }
};
