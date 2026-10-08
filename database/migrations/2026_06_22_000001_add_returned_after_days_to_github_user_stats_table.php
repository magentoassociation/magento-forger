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
            $table->unsignedInteger('returned_after_days')->nullable()->after('reviews_in_window');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn('returned_after_days');
        });
    }
};
