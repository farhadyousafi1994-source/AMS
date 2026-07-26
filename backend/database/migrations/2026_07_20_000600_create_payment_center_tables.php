<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized Payment Center. Every module (HR, subcontractors, procurement,
 * assets, investors, expenses…) raises a payment_request; a configurable
 * multi-level approval workflow gates it; the Finance Officer processes the
 * approved ones from one place. Approvals are recorded step-by-step for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_no')->nullable();
            $table->string('type');                 // salary|subcontractor|supplier|procurement|material|asset|advance|sub_advance|investor_withdrawal|expense|office_expense|petty_cash|other
            $table->string('payee_name');
            $table->string('payee_type')->nullable();  // model class for the payee (optional link)
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);
            $table->decimal('requested_amount', 16, 2)->default(0);
            $table->decimal('approved_amount', 16, 2)->nullable();
            $table->decimal('paid_amount', 16, 2)->nullable();
            $table->string('priority')->default('normal');   // low|normal|high|urgent
            $table->string('status')->default('pending');    // pending|approved|rejected|paid|on_hold
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('payment_method')->nullable();    // cash|bank|cheque|hawala
            $table->string('reference')->nullable();         // cheque no / bank ref
            $table->text('notes')->nullable();
            $table->boolean('fingerprint_verified')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('needed_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('payment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('role')->nullable();          // role expected to approve this level
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['payment_request_id', 'level']);
        });

        Schema::create('payment_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();          // applies to this payment type (null = any)
            $table->decimal('min_amount', 16, 2)->default(0);   // in base currency
            $table->decimal('max_amount', 16, 2)->nullable();   // null = no upper bound
            $table->json('levels');                      // ordered list of roles, e.g. ["Site Supervisor","President"]
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_approval_rules');
        Schema::dropIfExists('payment_approvals');
        Schema::dropIfExists('payment_requests');
    }
};
