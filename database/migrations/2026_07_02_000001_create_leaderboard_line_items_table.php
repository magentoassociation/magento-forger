<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_line_items', function (Blueprint $table) {
            $table->id();
            $table->string('login');
            $table->string('board');            // contributor | maintainer
            $table->string('action');           // Action enum value
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('contributed_at')->nullable();
            $table->decimal('points', 12, 4)->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            // The drill-down reads every row for one (login, board).
            $table->index(['login', 'board']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_line_items');
    }
};
