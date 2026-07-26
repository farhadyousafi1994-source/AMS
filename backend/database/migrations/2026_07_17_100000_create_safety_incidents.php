<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HSE / Safety Incident register — a standard enterprise construction module
 * (Procore Safety / Autodesk BIM 360). Captures hazards, near-misses,
 * incidents and accidents on a project or company-wide, with severity,
 * immediate + corrective action, lost-time tracking and an open→closed
 * investigation lifecycle. Feeds the executive "Safety Incidents" KPI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();                    // INC-####
            $table->string('type')->default('incident');           // hazard | near_miss | incident | accident
            $table->string('severity')->default('low');            // low | medium | high | critical
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('incident_date');
            $table->string('incident_time', 10)->nullable();
            $table->string('people_involved')->nullable();
            $table->unsignedInteger('injured_count')->default(0);
            $table->unsignedInteger('lost_time_days')->default(0);
            $table->text('immediate_action')->nullable();
            $table->text('corrective_action')->nullable();
            // open → investigating → action_pending → closed
            $table->string('status')->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_by_name')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_incidents');
    }
};
