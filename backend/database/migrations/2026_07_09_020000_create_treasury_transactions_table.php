<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The company's General Budget as an auditable ledger. Every movement
        // is a row: money in (deposit / project receipt) or out (allocation to
        // a project / withdrawal). Project receipts stay "reserved" while the
        // project runs and are released into the available balance when it
        // completes — so available + reserved always explains the total.
        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investment_id')->nullable(); // cap-table row that auto-created this allocation
            $table->string('direction');                    // in | out
            $table->string('kind')->default('adjustment');  // deposit|withdrawal|allocation|project_receipt|adjustment
            $table->string('status')->default('active');    // active | reserved
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);     // locked at entry
            $table->decimal('amount_base', 18, 2)->default(0);
            $table->date('tx_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
