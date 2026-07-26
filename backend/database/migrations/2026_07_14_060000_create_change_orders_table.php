<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change Orders (Variation Orders) — a standard construction module (Procore /
 * Buildertrend). A formal change to a project's scope, cost and/or time that
 * the owner approves. On approval the project's contract value is revised
 * (original + sum of approved change orders), which flows into the financing
 * meters. The original contract value is preserved for the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Preserve the original contract sum; contract_value becomes the revised sum.
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('original_contract_value', 16, 2)->nullable()->after('contract_value');
        });
        \Illuminate\Support\Facades\DB::table('projects')->update([
            'original_contract_value' => \Illuminate\Support\Facades\DB::raw('contract_value'),
        ]);

        Schema::create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();           // CO-####
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reason')->nullable();          // owner request | design change | site condition | error
            $table->string('kind')->default('addition');   // addition | deduction | no_cost
            // draft → submitted → approved | rejected
            $table->string('status')->default('draft');
            $table->decimal('cost_impact', 18, 2)->default(0);   // + for addition, − stored positive with kind
            $table->string('currency', 10)->default('AFN');
            $table->decimal('rate', 16, 4)->default(1);
            $table->decimal('cost_impact_base', 18, 2)->default(0);
            $table->integer('time_impact_days')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->date('co_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_orders');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('original_contract_value');
        });
    }
};
