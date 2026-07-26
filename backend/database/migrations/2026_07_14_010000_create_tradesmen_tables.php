<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-project subcontractors (استادکاران). A tradesman exists once and works
 * across many projects; each per-project engagement is the existing
 * `subcontractors` row, now linked back to a tradesman. Weekly payments already
 * live on subcontractor_payments. Adds a fingerprint id for the payout scanner
 * and a work-measurement ledger. See docs/supervisor-module-spec.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tradesmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();        // SUB-####
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('trade')->nullable();        // mason, plaster, steel-fixing…
            $table->string('cnic')->nullable();         // تذکره / ID number
            $table->string('fingerprint_id')->nullable()->index(); // scanner match key
            $table->decimal('default_rate', 16, 2)->default(0);    // per unit of work
            $table->string('rate_unit')->nullable();    // m², m³, day…
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_name')->nullable();
            $table->string('photo_mime')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Link each per-project engagement to its cross-project tradesman.
        Schema::table('subcontractors', function (Blueprint $table) {
            $table->foreignId('tradesman_id')->nullable()->after('project_id')
                ->constrained('tradesmen')->nullOnDelete();
        });

        // Work measurement (اندازه‌گیری کار): qty × rate = amount, per project.
        Schema::create('work_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tradesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('measure_date');
            $table->string('description')->nullable();
            $table->string('unit')->default('m2');       // m2, m3, running-m, day, lump
            $table->decimal('quantity', 16, 3)->default(0);
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0); // qty × unit_price
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_measurements');
        Schema::table('subcontractors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tradesman_id');
        });
        Schema::dropIfExists('tradesmen');
    }
};
