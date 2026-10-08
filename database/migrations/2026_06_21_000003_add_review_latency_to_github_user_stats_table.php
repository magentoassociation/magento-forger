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
            $table->decimal('median_time_to_review_hours', 10, 2)->nullable()->after('longest_streak_weeks');
            $table->decimal('median_time_to_claim_days', 10, 2)->nullable()->after('median_time_to_review_hours');
            $table->unsignedInteger('reviews_in_window')->default(0)->after('median_time_to_claim_days');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn(['median_time_to_review_hours', 'median_time_to_claim_days', 'reviews_in_window']);
        });
    }
};
