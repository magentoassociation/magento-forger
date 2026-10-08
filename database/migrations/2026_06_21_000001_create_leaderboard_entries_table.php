<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->string('login')->index();
            $table->string('board');                 // contributor | maintainer
            $table->string('window')->default('rolling12');
            $table->decimal('score', 12, 4)->default(0);
            $table->json('breakdown')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['login', 'board', 'window']);
            $table->index(['board', 'window', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
