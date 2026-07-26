<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();               // EMP-0001

            // Personal
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('grandfather_name')->nullable();
            $table->string('tazkira')->nullable();
            $table->string('gender')->nullable();              // male|female
            $table->date('dob')->nullable();
            $table->string('marital_status')->nullable();      // single|married
            $table->string('nationality')->nullable()->default('افغان');
            $table->string('phone');
            $table->string('phone2')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();

            // Employment
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employment_type')->default('permanent'); // permanent|contract|daily_wage
            $table->date('join_date')->nullable();
            $table->string('status')->default('active');       // active|on_leave|inactive
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('license')->nullable();
            $table->json('assigned_projects')->nullable();

            // Payroll
            $table->decimal('basic_salary', 16, 2)->nullable();
            $table->string('salary_currency', 10)->default('AFN');
            $table->string('payment_method')->nullable();      // cash|bank|hawala
            $table->string('bank_details')->nullable();
            $table->json('allowances')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
