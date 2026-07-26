<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();               // AST-0001
            $table->string('name');
            $table->string('category')->default('equipment'); // heavy_equipment|vehicle|tool|equipment
            $table->string('tracking')->default('unit');       // unit | count
            $table->unsignedInteger('quantity_total')->default(1);
            $table->unsignedInteger('allocated')->default(0);  // allocated to projects
            $table->string('unit')->nullable();                // piece|set (for count mode)
            $table->string('status')->default('available');    // available|in_use|maintenance|retired
            $table->string('location')->nullable();
            $table->string('serial')->nullable();              // plate / serial (unit mode)
            $table->string('condition')->nullable();           // new|good|fair|needs_repair (unit mode)
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 16, 2)->nullable();
            $table->string('currency', 10)->default('AFN');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
