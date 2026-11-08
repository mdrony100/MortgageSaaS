<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', fn (Blueprint $t) => $t->boolean('org_wide_access')->default(false));
    }

    public function down(): void
    {
        Schema::table('invitations', fn (Blueprint $t) => $t->dropColumn('org_wide_access'));
    }
};
