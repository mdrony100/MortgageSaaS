<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_inquiries', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 190);
            $t->string('company', 150);
            $t->string('role', 120)->nullable();
            $t->string('inquiry_type', 30);
            $t->text('message');
            $t->string('status', 20)->default('new');
            $t->boolean('is_spam')->default(false);
            $t->string('spam_reason', 40)->nullable();
            $t->string('source_hash', 64)->nullable();
            $t->string('notification_status', 30)->default('pending');
            $t->string('notification_error', 120)->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['is_spam', 'status', 'inquiry_type', 'created_at']);
        });
        Schema::create('website_inquiry_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('website_inquiry_id')->constrained('website_inquiries')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_inquiry_notes');
        Schema::dropIfExists('website_inquiries');
    }
};
