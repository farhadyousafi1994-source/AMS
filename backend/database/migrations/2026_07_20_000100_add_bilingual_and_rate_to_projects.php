<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Bilingual project name (Dari alongside the English name).
            $table->string('name_fa')->nullable()->after('name');
            // Locked AFN-per-unit rate at entry for the contract value.
            $table->decimal('rate', 16, 6)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['name_fa', 'rate']);
        });
    }
};
