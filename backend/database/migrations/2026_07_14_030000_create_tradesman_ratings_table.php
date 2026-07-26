<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontractor performance feedback — one rating per project, immutable once
 * given (no edit endpoint). The average across projects is the reputation
 * score shown on the subcontractor page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tradesman_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tradesman_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');            // 1..5 overall
            $table->unsignedTinyInteger('quality')->nullable();     // 1..5
            $table->unsignedTinyInteger('timeliness')->nullable();  // 1..5
            $table->unsignedTinyInteger('safety')->nullable();      // 1..5
            $table->text('comment')->nullable();
            $table->string('rated_by_name')->nullable();     // engineer / who rated
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tradesman_ratings');
    }
};
