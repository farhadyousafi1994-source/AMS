<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('category')->default('General');
            $table->string('payee')->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 10)->default('AFN');
            $table->decimal('amount', 16, 2);
            $table->decimal('rate', 16, 4)->default(1);       // exchange rate LOCKED at entry time
            $table->decimal('amount_base', 16, 2)->default(0); // amount * rate, in base currency
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
