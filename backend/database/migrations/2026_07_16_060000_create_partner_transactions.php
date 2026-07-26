<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shareholder (partner) equity ledger — deposits (capital in) and withdrawals
 * (drawings). Both post to the General Budget so the company cash stays in sync.
 * Profit-share and expense-share are computed, not stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type');                         // deposit | withdrawal
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);
            $table->decimal('amount_base', 18, 2)->default(0);
            $table->date('tx_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('treasury_transaction_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'partner_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
    }
};
