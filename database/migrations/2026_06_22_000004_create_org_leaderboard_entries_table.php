<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('board');                 // contributor | maintainer
            $table->string('window')->default('rolling12');
            $table->decimal('score', 14, 4)->default(0);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'board', 'window']);
            $table->index(['board', 'window', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_leaderboard_entries');
    }
};
