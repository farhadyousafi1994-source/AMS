<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options Registry — a single bilingual, editable source for every dropdown in
 * the system (units, project types, statuses, phases, provinces, payment
 * methods, categories, …). Admins add/edit values from one screen and every
 * select box picks them up, in English and Dari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('group');                 // unit, project_type, province, …
            $table->string('code');                  // stable machine value
            $table->string('label_en');
            $table->string('label_fa')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false); // seeded defaults — editable but not deletable
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'group', 'code']);
            $table->index(['company_id', 'group', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
