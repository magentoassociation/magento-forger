<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_eligibilities', function (Blueprint $table) {
            // Maintainers who leave the GitHub team are kept but marked inactive.
            $table->boolean('active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_eligibilities', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
