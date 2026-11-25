<?php

use App\Billing\PageCredits;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: new plan columns, a page-credit ledger, offline payment requests,
 * and public demo requests. Existing rows keep working.
 *
 * After the columns exist, every organization already in the database receives one
 * opening balance equal to its plan's monthly page quota (or 500 pages when the
 * plan has no quota yet). That grant is recorded on the ledger. Run DatabaseSeeder
 * as well so Trial, Pro and Business exist and placeholder prices and top-up packs
 * are filled in. See the deploy notes on the pull request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->boolean('listed_publicly')->default(false);
            $t->unsignedInteger('monthly_page_quota')->default(500);
            $t->unsignedInteger('trial_page_allowance')->default(0);
            $t->unsignedInteger('per_file_page_cap')->nullable();
            $t->jsonb('topup_packs')->nullable();
            $t->string('summary', 280)->nullable();
        });

        DB::table('plans')->where('name', 'Starter')->update(['monthly_page_quota' => 500, 'listed_publicly' => true, 'summary' => 'For a small team getting started with document review.']);
        DB::table('plans')->where('name', 'Team')->update(['monthly_page_quota' => 2000, 'summary' => 'For a growing processing team.']);
        DB::table('plans')->where('name', 'Enterprise')->update(['monthly_page_quota' => 10000, 'summary' => 'For a high-volume review desk.']);

        Schema::create('page_credit_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $t->integer('balance')->default(0);
            $t->unsignedInteger('used_this_period')->default(0);
            $t->unsignedInteger('allocated_this_period')->default(0);
            $t->boolean('trial_granted')->default(false);
            $t->timestamp('period_starts_at')->nullable();
            $t->timestamp('period_ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('page_credit_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40);
            $t->integer('pages');
            $t->integer('balance_after');
            $t->string('reason', 500);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('payment_request_id')->nullable();
            $t->foreignId('loan_file_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('document_version_id')->nullable();
            $t->string('pipeline', 20)->nullable();
            $t->string('idempotency_key', 160)->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['organization_id', 'idempotency_key']);
            $t->index(['organization_id', 'id']);
        });

        Schema::create('payment_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->string('kind', 20);
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('pack_pages')->nullable();
            $t->string('pack_label', 80)->nullable();
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->text('note')->nullable();
            $t->string('status', 20)->default('pending');
            $t->string('payment_method', 80)->nullable();
            $t->string('payment_reference', 120)->nullable();
            $t->text('payment_note')->nullable();
            $t->text('decision_reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('paid_offline_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'status']);
            $t->index(['status', 'id']);
        });

        Schema::table('page_credit_entries', function (Blueprint $t) {
            $t->foreign('payment_request_id')->references('id')->on('payment_requests')->nullOnDelete();
        });

        Schema::create('demo_requests', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('company', 150);
            $t->string('email', 190);
            $t->string('phone', 40);
            $t->string('monthly_volume', 120);
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new');
            $t->boolean('is_spam')->default(false);
            $t->string('spam_reason', 40)->nullable();
            $t->string('source_hash', 64)->nullable();
            $t->string('notification_status', 30)->default('pending');
            $t->string('notification_error', 120)->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'id']);
        });

        Schema::create('demo_request_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('demo_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        foreach (Organization::query()->orderBy('id')->cursor() as $org) {
            app(PageCredits::class)->provision($org, null, 'Starting page allocation so this workspace can keep processing documents. Granted once when page credits were introduced.');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_request_notes');
        Schema::dropIfExists('demo_requests');
        Schema::table('page_credit_entries', function (Blueprint $t) {
            $t->dropForeign(['payment_request_id']);
        });
        Schema::dropIfExists('page_credit_entries');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('page_credit_accounts');
        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn(['is_active', 'listed_publicly', 'monthly_page_quota', 'trial_page_allowance', 'per_file_page_cap', 'topup_packs', 'summary']);
        });
    }
};
