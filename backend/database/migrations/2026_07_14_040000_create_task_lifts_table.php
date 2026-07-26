<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifts — the layer of concrete poured (or fill compacted) in one operation,
 * under a work-breakdown task. Each lift is a quality hold point: it is poured,
 * then inspected (pass/fail) before the next lift proceeds. Poured quantity is
 * the material consumed for that lift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->unsignedInteger('seq');                    // Lift 1, 2, 3…
            $table->string('lift_type')->default('concrete');  // concrete | earthwork | scaffold | other
            $table->string('description')->nullable();
            $table->string('unit', 20)->default('m3');         // m3 | m2 | mm | m
            $table->decimal('planned_qty', 16, 3)->default(0);
            $table->decimal('poured_qty', 16, 3)->nullable();  // actual material consumed
            $table->decimal('height_m', 8, 3)->nullable();     // lift height
            $table->date('pour_date')->nullable();
            // planned → poured → passed | failed
            $table->string('status')->default('planned');
            $table->string('inspected_by')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->string('inspection_result')->nullable();   // pass | fail
            $table->text('inspection_note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['task_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lifts');
    }
};
