<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standard payslip components on each payroll line.
        Schema::table('payroll_items', function (Blueprint $table) {
            foreach (['housing', 'transport', 'bonus', 'tax', 'loan', 'advance', 'gross'] as $col) {
                if (! Schema::hasColumn('payroll_items', $col)) {
                    $table->decimal($col, 18, 2)->default(0)->after('allowances');
                }
            }
            foreach (['present_days', 'leave_days'] as $col) {
                if (! Schema::hasColumn('payroll_items', $col)) {
                    $table->integer($col)->default(0)->after('absent_days');
                }
            }
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
        });

        // Leave requests / records.
        if (! Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->string('type')->default('annual'); // annual|sick|unpaid|maternity|other
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('days')->default(1);
                $table->boolean('paid')->default(true);
                $table->string('status')->default('pending'); // pending|approved|rejected
                $table->text('reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_runs', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['housing', 'transport', 'bonus', 'tax', 'loan', 'advance', 'gross', 'present_days', 'leave_days']);
        });
    }
};
