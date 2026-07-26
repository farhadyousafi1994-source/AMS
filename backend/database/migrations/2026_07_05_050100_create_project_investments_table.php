<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_company')->default(false);   // the company itself as a participant
            $table->string('participant_name');              // cached: investor name or the company
            $table->decimal('capital', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);      // locked at entry
            $table->decimal('profit_percent', 6, 2)->default(0); // negotiated, independent of capital
            $table->string('basis')->nullable();             // justification note
            $table->decimal('profit_received', 18, 2)->default(0);
            $table->string('phase')->nullable();             // kept for a future per-phase model
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_investments');
    }
};
