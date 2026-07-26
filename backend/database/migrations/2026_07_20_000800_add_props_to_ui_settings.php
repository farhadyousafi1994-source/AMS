<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich per-element configuration for the Control Room. Beyond hidden/order/
 * label, a UI element can carry arbitrary flags — disabled, required, readonly,
 * expanded, sortable, filterable, exportable, editable, default_value, role —
 * so cards, table columns, toolbar actions and form fields are all controllable
 * from one place without code changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ui_settings', function (Blueprint $table) {
            $table->json('props')->nullable()->after('label_override');
        });
    }

    public function down(): void
    {
        Schema::table('ui_settings', function (Blueprint $table) {
            $table->dropColumn('props');
        });
    }
};
