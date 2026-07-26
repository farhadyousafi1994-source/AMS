<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 1 of the supervisor & site-management module: field cash purchases with
 * engineer approval, cash advances, mandatory receipt upload, and a filterable
 * invoice archive. Field purchases are deliberately separate from the formal
 * supplier purchase-orders module. See docs/supervisor-module-spec.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-project assignment: supervisors/engineers see only their projects.
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('site_role')->nullable(); // supervisor | engineer (informational)
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        // Petty-cash threshold: a request at/under this auto-approves. 0 = all
        // purchases need engineer approval.
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('petty_cash_limit', 18, 2)->default(0)->after('contract_value');
        });

        // Fixed-ish trade/category list; seeded, editable by admin.
        Schema::create('purchase_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // supervisor who raised it
            $table->foreignId('category_id')->nullable()->constrained('purchase_categories')->nullOnDelete();
            $table->string('code')->nullable();        // PR-####
            $table->string('title')->nullable();
            $table->json('items')->nullable();         // [{name, qty, unit, est_price}]
            $table->decimal('estimated_total', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            // pending | approved | rejected | purchased | closed
            $table->string('status')->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_given', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('given_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // The invoice archive: the phone photo captured at purchase IS the entry.
        Schema::create('site_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('purchase_categories')->nullOnDelete();
            $table->string('source')->default('purchase'); // purchase | rental | other
            $table->string('vendor')->nullable();
            $table->decimal('actual_total', 18, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->string('image_path')->nullable();
            $table->string('image_name')->nullable();
            $table->string('image_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('invoice_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_invoices');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('purchase_categories');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('petty_cash_limit');
        });
        Schema::dropIfExists('project_user');
    }
};
