<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('title');
            $table->string('party_name');
            $table->string('party_type')->default('individual'); // individual|company|government
            $table->string('party_phone')->nullable();
            $table->string('direction')->default('subcontractor'); // client (money in) | subcontractor (money out)
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);     // locked at signing
            $table->string('status')->default('active');    // draft|active|completed|cancelled
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('scope')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
