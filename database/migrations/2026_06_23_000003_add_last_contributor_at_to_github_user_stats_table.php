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
            $table->timestamp('last_contributor_at')->nullable()->after('last_contribution_at');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn('last_contributor_at');
        });
    }
};
