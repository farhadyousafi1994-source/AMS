<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The credit/debit ledger. direction=in: they gave us money (their
        // balance goes up — we owe them). direction=out: we paid them (their
        // balance goes down; below zero means they owe us). Confirmed rows
        // also move the General Budget; pending rows are promises only.
        Schema::create('party_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');                    // in | out
            $table->string('status')->default('confirmed'); // confirmed | pending
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);     // locked at entry
            $table->decimal('amount_base', 18, 2)->default(0);
            $table->date('tx_date');
            $table->string('method')->default('cash');      // cash|bank|hawala|other
            $table->string('basis')->nullable();            // "according to what"
            $table->string('handled_by')->nullable();       // who physically received / paid
            $table->string('attachment_path')->nullable();  // receipt image
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Confirmed party money is real cash in/out of the General Budget —
        // keep the auto-created treasury row linked so they stay in step.
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('party_transaction_id')->nullable()->after('investment_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropColumn('party_transaction_id');
        });
        Schema::dropIfExists('party_transactions');
    }
};
