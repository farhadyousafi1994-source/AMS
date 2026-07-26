<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Office & Home expenses. Instead of duplicate tables, the existing `expenses`
 * table gains a `type` discriminator (project | office | home) plus the fields
 * these categories need (payment method, vendor, approval, attachment). Adds an
 * equal-share `partners` table and an `expense_budgets` table for Home tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('type')->default('project')->after('project_id');   // project | office | home
            $table->string('payment_method')->nullable()->after('payee');      // cash | bank | hawala | card
            $table->string('vendor')->nullable()->after('payment_method');
            $table->string('approval_status')->default('approved')->after('vendor'); // pending | approved | rejected
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->index(['company_id', 'type', 'expense_date']);
        });

        // Equal partners (engineers). Office overhead is split by share_percent.
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('share_percent', 6, 3)->default(25);   // equal by default
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Budgets for Home (and optionally Office) — budget vs actual.
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('home');       // home | office
            $table->string('category')->nullable();        // null = whole type
            $table->string('period', 7);                   // YYYY-MM (monthly)
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'type', 'category', 'period'], 'expense_budget_unique');
        });

        // Link an auto-created treasury withdrawal to its expense.
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_id')->nullable()->after('party_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropColumn('expense_id');
        });
        Schema::dropIfExists('expense_budgets');
        Schema::dropIfExists('partners');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['type', 'payment_method', 'vendor', 'approval_status', 'approved_at',
                'attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};
