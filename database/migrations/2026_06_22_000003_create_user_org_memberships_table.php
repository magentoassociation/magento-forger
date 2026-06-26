<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_org_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('login')->index();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('from_date')->nullable();   // null = since the beginning
            $table->date('to_date')->nullable();     // null = current
            $table->string('source')->default('manual'); // manual | domain | profile
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_org_memberships');
    }
};
