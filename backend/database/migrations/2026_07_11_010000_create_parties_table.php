<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anyone the company has a money relationship with outside the cap
        // table: lenders, relatives, banks, exchanges (صرافی). One master row
        // per party; the ledger below gives each a running credit/debit.
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();      // PTY-####
            $table->string('name');
            $table->string('type')->default('person'); // person|company|bank|exchange|relative|other
            $table->string('phone')->nullable();
            $table->string('relation')->nullable();  // e.g. "برادر رئیس", "بانک همکار"
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
