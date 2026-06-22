<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_user_stats', function (Blueprint $table) {
            $table->id();
            $table->string('login')->unique();
            $table->timestamp('first_contribution_at')->nullable();
            $table->timestamp('last_contribution_at')->nullable();
            $table->unsignedInteger('current_gap_days')->nullable();
            $table->unsignedInteger('current_streak_weeks')->default(0);
            $table->unsignedInteger('longest_streak_weeks')->default(0);
            $table->decimal('contributor_score', 12, 4)->default(0);
            $table->decimal('maintainer_score', 12, 4)->default(0);
            $table->decimal('contributor_score_prev', 12, 4)->default(0);
            $table->decimal('maintainer_score_prev', 12, 4)->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_user_stats');
    }
};
