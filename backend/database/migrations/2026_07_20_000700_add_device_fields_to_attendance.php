<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('check_in')->nullable()->after('note');
            $table->string('check_out')->nullable()->after('check_in');
            $table->string('source')->default('manual')->after('check_out'); // manual|device
            $table->foreignId('device_id')->nullable()->after('source')->constrained('fingerprint_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn(['check_in', 'check_out', 'source']);
        });
    }
};
