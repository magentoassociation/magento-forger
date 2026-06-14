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
        // Tables were intentionally removed with the company subsystem
    }
};
