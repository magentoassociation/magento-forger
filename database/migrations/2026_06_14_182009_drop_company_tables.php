<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('company_owners');
        Schema::dropIfExists('company_affiliations');
        Schema::dropIfExists('companies');
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be rolled back: the company tables were permanently dropped with the company subsystem.');
    }
};
