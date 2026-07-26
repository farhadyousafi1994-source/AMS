<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich employee profile: studies (education), documents (degree, national ID,
 * passport, license…) and a specializations tag list. Salary history and
 * attendance already live on payroll_items / attendance_records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->json('specializations')->nullable()->after('license'); // skill tags
        });

        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('degree');                 // Bachelor, Diploma, Baccalaureate…
            $table->string('field')->nullable();      // Civil Engineering…
            $table->string('institution')->nullable();
            $table->string('year_from', 9)->nullable();
            $table->string('year_to', 9)->nullable();
            $table->string('grade')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // degree | national_id | passport | license | contract | certificate | other
            $table->string('doc_type')->default('other');
            $table->string('title');
            $table->string('number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_educations');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('specializations');
        });
    }
};
