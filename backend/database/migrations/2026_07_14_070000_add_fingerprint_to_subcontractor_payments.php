<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontractor payments can be confirmed by the subcontractor's fingerprint —
 * proof they actually received the money. Verified against the registered
 * fingerprint id (virtual scanner now; hardware device integrates later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontractor_payments', function (Blueprint $table) {
            $table->boolean('fingerprint_confirmed')->default(false)->after('note');
            $table->timestamp('fingerprint_confirmed_at')->nullable()->after('fingerprint_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_payments', function (Blueprint $table) {
            $table->dropColumn(['fingerprint_confirmed', 'fingerprint_confirmed_at']);
        });
    }
};
