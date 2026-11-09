<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner console: sign-in disablement, organization suspension/approval/trial/limit overrides,
 * platform approval queue, admin notes, and audited support (impersonation) sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('disabled_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::table('organizations', function (Blueprint $t) {
            $t->timestamp('suspended_at')->nullable();
            $t->string('suspension_reason', 500)->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->string('approval_status', 20)->default('approved');
            $t->string('approval_reason', 500)->nullable();
            $t->timestamp('approval_decided_at')->nullable();
            $t->integer('file_limit_override')->nullable();
            $t->integer('member_limit_override')->nullable();
            $t->bigInteger('storage_limit_bytes_override')->nullable();
        });
        Schema::create('organization_admin_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('platform_requests', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('pending');
            $t->jsonb('payload')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_reason', 1000)->nullable();
            $t->timestamps();
            $t->index(['status', 'type', 'created_at']);
        });
        Schema::create('impersonation_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->constrained('users');
            $t->foreignId('target_user_id')->constrained('users');
            $t->string('reason', 500);
            $t->timestamp('started_at');
            $t->timestamp('expires_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('end_reason', 30)->nullable();
            $t->timestamps();
        });
        Schema::table('website_inquiries', function (Blueprint $t) {
            $t->foreignId('converted_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $t->timestamp('converted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('website_inquiries', fn (Blueprint $t) => $t->dropConstrainedForeignId('converted_organization_id'));
        Schema::table('website_inquiries', fn (Blueprint $t) => $t->dropColumn('converted_at'));
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('platform_requests');
        Schema::dropIfExists('organization_admin_notes');
        Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn(['suspended_at', 'suspension_reason', 'trial_ends_at', 'approval_status', 'approval_reason', 'approval_decided_at', 'file_limit_override', 'member_limit_override', 'storage_limit_bytes_override']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['disabled_at', 'last_login_at']));
    }
};
