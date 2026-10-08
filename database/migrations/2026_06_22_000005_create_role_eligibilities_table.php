<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->string('login')->index();
            $table->string('role'); // contributor | maintainer
            $table->timestamps();

            $table->unique(['login', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_eligibilities');
    }
};
