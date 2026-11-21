<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('subject', 160);
            $t->string('category', 32);
            $t->string('priority', 16);
            $t->string('status', 16)->default('open');
            $t->timestamps();
            $t->index(['organization_id', 'status']);
            $t->index(['organization_id', 'user_id']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_ticket_id')->constrained('support_tickets');
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->boolean('staff')->default(false);
            $t->text('body');
            $t->timestamps();
            $t->index(['support_ticket_id', 'id']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_ticket_message_id')->constrained('support_ticket_messages');
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('disk');
            $t->string('storage_key')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('scan_status');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
