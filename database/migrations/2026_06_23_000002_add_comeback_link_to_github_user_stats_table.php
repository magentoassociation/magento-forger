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
            $table->string('comeback_url')->nullable()->after('returned_after_days');
            $table->string('comeback_title')->nullable()->after('comeback_url');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn(['comeback_url', 'comeback_title']);
        });
    }
};
