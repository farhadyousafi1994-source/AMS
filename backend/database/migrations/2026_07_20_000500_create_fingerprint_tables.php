<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fingerprint / biometric subsystem — device registry, per-person enrolments
 * (templates only, never raw images) and a per-company policy. Designed to be
 * device-agnostic: a driver layer (Simulator, ZKTeco, DigitalPersona, WebAuthn…)
 * sits on top of the same tables so new brands plug in without schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->default('simulator');      // zkteco|digitalpersona|secugen|mantra|webauthn|simulator|other
            $table->string('model')->nullable();
            $table->string('connection')->default('bridge');     // usb|network|bridge|webauthn|simulator
            $table->string('host')->nullable();                  // network/bridge devices
            $table->unsignedInteger('port')->nullable();
            $table->string('serial')->nullable();
            $table->json('settings')->nullable();                // device-specific knobs
            $table->string('status')->default('unknown');        // online|offline|unknown
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active']);
        });

        Schema::create('fingerprint_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('enrollable');                        // tradesman | user | employee
            $table->string('finger')->default('right_thumb');
            $table->text('template');                            // opaque biometric template — encrypted at rest
            $table->string('template_hash', 128)->nullable();    // for fast 1:1 lookup
            $table->unsignedTinyInteger('quality')->nullable();  // 0-100
            $table->foreignId('device_id')->nullable()->constrained('fingerprint_devices')->nullOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'enrollable_type', 'enrollable_id', 'finger'], 'fp_enroll_unique');
            $table->index('template_hash');
        });

        Schema::create('fingerprint_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('enabled')->default(false);
            $table->string('enforcement')->default('optional');  // off|optional|required
            $table->boolean('allow_override')->default(true);     // manager override when it can't scan
            $table->boolean('allow_pin_fallback')->default(false);
            $table->boolean('fallback_when_unavailable')->default(true);
            $table->unsignedTinyInteger('min_quality')->default(40);
            $table->timestamps();
        });

        // Richer audit on the payment: how identity was verified, and by whom.
        Schema::table('subcontractor_payments', function (Blueprint $table) {
            $table->string('fingerprint_method')->nullable()->after('fingerprint_confirmed_at'); // template|override|pin|id
            $table->foreignId('verified_by')->nullable()->after('fingerprint_method')->constrained('users')->nullOnDelete();
            $table->string('verify_note')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['fingerprint_method', 'verify_note']);
        });
        Schema::dropIfExists('fingerprint_settings');
        Schema::dropIfExists('fingerprint_enrollments');
        Schema::dropIfExists('fingerprint_devices');
    }
};
