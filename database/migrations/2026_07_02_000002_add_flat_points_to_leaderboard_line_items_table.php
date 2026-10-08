<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaderboard_line_items', function (Blueprint $table) {
            // Flat (no recency decay) points and the UTC calendar month, so the
            // monthly drill-down can filter + sum without re-deriving.
            $table->decimal('points_flat', 12, 4)->default(0)->after('points');
            $table->string('month')->nullable()->after('contributed_at'); // YYYY-MM (UTC)

            $table->index(['login', 'board', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('leaderboard_line_items', function (Blueprint $table) {
            $table->dropIndex(['login', 'board', 'month']);
            $table->dropColumn(['points_flat', 'month']);
        });
    }
};
