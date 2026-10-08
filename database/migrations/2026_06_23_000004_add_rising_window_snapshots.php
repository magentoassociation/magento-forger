<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('login');
            $table->decimal('contributor_score', 12, 4)->default(0);
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['login', 'captured_at']);
            $table->index('captured_at');
        });

        Schema::table('github_user_stats', function (Blueprint $table) {
            // Contributor score as of the start of the "Rising" window, used to
            // compute a fixed-timeframe delta instead of a per-run delta.
            $table->decimal('rising_baseline_score', 12, 4)->default(0)->after('maintainer_score_prev');
        });
    }

    public function down(): void
    {
        Schema::table('github_user_stats', function (Blueprint $table) {
            $table->dropColumn('rising_baseline_score');
        });

        Schema::dropIfExists('github_score_snapshots');
    }
};
