<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 2: worker registration (anti-ghost-worker) and daily field attendance
 * with photo + GPS. Distinct from the office HR AttendanceRecord/employee model
 * — this is day-labor tied to a registered Worker. See supervisor-module-spec.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();       // WKR-#### generated at hire
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('trade')->nullable();       // mason, laborer, carpenter…
            $table->decimal('default_wage', 18, 2)->default(0); // prefill only; authority is the record
            $table->string('photo_path')->nullable();
            $table->string('photo_name')->nullable();
            $table->string('photo_mime')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('worker_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status')->default('present'); // present | absent | half
            $table->string('task')->nullable();
            $table->decimal('day_rate', 18, 2)->default(0); // wage entered per record (confirmed policy)
            $table->string('photo_path')->nullable();
            $table->string('photo_name')->nullable();
            $table->string('photo_mime')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('client_uuid')->nullable(); // offline-sync dedupe key
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One mark per worker per day; offline sync upserts on this.
            $table->unique(['worker_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_attendances');
        Schema::dropIfExists('workers');
    }
};
