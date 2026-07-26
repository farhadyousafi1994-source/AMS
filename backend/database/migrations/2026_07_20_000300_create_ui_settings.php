<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Control Room: per-company visibility & ordering of menus, pages,
        // tabs, inputs and table features. A key is a dotted path such as
        // "menu.ProjectsGroup", "sub.ChangeOrders",
        // "page.projects.tab.financing" or "page.projects.table.advanced_search".
        Schema::create('ui_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key', 160);
            $table->boolean('hidden')->default(false);
            $table->integer('sort_order')->nullable();
            $table->string('label_override')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'hidden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_settings');
    }
};
