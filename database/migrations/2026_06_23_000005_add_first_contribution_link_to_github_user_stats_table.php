<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->string('first_contribution_url')->nullable()->after('first_contribution_at');
            $table->string('first_contribution_title')->nullable()->after('first_contribution_url');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn(['first_contribution_url', 'first_contribution_title']);
        });
    }
};
