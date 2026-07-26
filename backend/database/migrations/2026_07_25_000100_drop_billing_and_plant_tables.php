<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Billing (BOQ / IPC) and Plant & Fuel modules were removed from the product.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ipc_lines');
        Schema::dropIfExists('ipc_certificates');
        Schema::dropIfExists('boq_items');
        Schema::dropIfExists('plant_logs');
    }

    public function down(): void
    {
        // Intentionally irreversible — the modules no longer exist.
    }
};
