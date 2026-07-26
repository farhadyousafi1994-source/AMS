<?php

namespace App\Http\Controllers\Fingerprint;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FingerprintDevice;
use App\Models\FingerprintEnrollment;
use App\Models\FingerprintSetting;
use App\Models\SubcontractorPayment;
use App\Services\Fingerprint\FingerprintManager;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Enrolment, verification and policy for the biometric subsystem. Templates are
 * stored encrypted (never raw images); matching is 1:1 against the enrolled
 * person; every verification is written to the audit log.
 */
class FingerprintController extends Controller
{
    /** enrollable_type token → model class. Extend as more entities enrol. */
    private const TYPES = [
        'tradesman' => \App\Models\Tradesman::class,
        'user' => \App\Models\User::class,
        'employee' => \App\Models\Employee::class,
    ];

    public function __construct(private FingerprintManager $manager) {}

    // ── Settings ──────────────────────────────────────────────────────────
    public function settings(): JsonResponse
    {
        return response()->json([
            'settings' => FingerprintSetting::current(),
            'brands' => $this->manager->brands(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'enforcement' => ['required', 'in:off,optional,required'],
            'allow_override' => ['boolean'],
            'allow_pin_fallback' => ['boolean'],
            'fallback_when_unavailable' => ['boolean'],
            'min_quality' => ['integer', 'min:0', 'max:100'],
        ]);
        $s = FingerprintSetting::current();
        $s->update($data);
        ActivityLog::log('updated', 'FingerprintSetting', 'Updated fingerprint policy');

        return response()->json($s);
    }

    // ── Capture / Enrolment ───────────────────────────────────────────────
    /** Live-scan on a device (works fully with the Simulator). */
    public function capture(Request $request): JsonResponse
    {
        $device = FingerprintDevice::findOrFail($request->integer('device_id'));

        return response()->json($this->manager->forDevice($device)->capture($device));
    }

    public function enrollments(Request $request): JsonResponse
    {
        [$type, $id] = $this->resolveEnrollable($request);

        return response()->json(
            FingerprintEnrollment::where('enrollable_type', $type)->where('enrollable_id', $id)
                ->get(['id', 'finger', 'quality', 'device_id', 'created_at'])
        );
    }

    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'finger' => ['required', 'string', 'max:40'],
            'template' => ['required', 'string'],
            'quality' => ['nullable', 'integer', 'min:0', 'max:100'],
            'device_id' => ['nullable', 'integer', 'exists:fingerprint_devices,id'],
        ]);
        [$type, $id] = $this->resolveEnrollable($request);

        $min = FingerprintSetting::current()->min_quality;
        if ($request->filled('quality') && $request->integer('quality') < $min) {
            throw ValidationException::withMessages(['quality' => "Scan quality too low (min {$min}). Please re-scan."]);
        }

        $enrollment = FingerprintEnrollment::updateOrCreate(
            ['company_id' => Tenant::id(), 'enrollable_type' => $type, 'enrollable_id' => $id, 'finger' => $request->string('finger')],
            [
                'template' => $request->string('template'),
                'template_hash' => hash('sha256', trim($request->input('template'))),
                'quality' => $request->integer('quality') ?: null,
                'device_id' => $request->integer('device_id') ?: null,
                'enrolled_by' => $request->user()?->id,
            ]
        );
        ActivityLog::log('created', 'FingerprintEnrollment', "Enrolled {$request->input('finger')} for {$request->input('enrollable_type')} #{$id}");

        return response()->json(['id' => $enrollment->id, 'finger' => $enrollment->finger, 'quality' => $enrollment->quality], 201);
    }

    public function removeEnrollment(FingerprintEnrollment $enrollment): JsonResponse
    {
        $enrollment->delete();
        ActivityLog::log('deleted', 'FingerprintEnrollment', "Removed enrolment #{$enrollment->id}");

        return response()->json(['message' => 'Removed.']);
    }

    // ── Verification ──────────────────────────────────────────────────────
    /** 1:1 verify a captured template against a person's enrolments. */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['template' => ['required', 'string']]);
        [$type, $id] = $this->resolveEnrollable($request);

        $result = $this->matchAgainst($type, $id, $request->input('template'));
        ActivityLog::log('viewed', 'FingerprintVerify', ($result['matched'] ? 'Matched' : 'No match')." for {$request->input('enrollable_type')} #{$id}");

        return response()->json($result);
    }

    /**
     * Verify identity at the moment a subcontractor collects payment, honouring
     * the company policy, and stamp the payment + audit log with the outcome.
     */
    public function verifyPayment(Request $request, SubcontractorPayment $payment): JsonResponse
    {
        $data = $request->validate([
            'template' => ['nullable', 'string'],
            'method' => ['nullable', 'in:template,override,pin,id'],
            'device_id' => ['nullable', 'integer', 'exists:fingerprint_devices,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = FingerprintSetting::current();
        $engagement = \App\Models\Subcontractor::find($payment->subcontractor_id);
        $tradesmanId = $engagement?->tradesman_id;
        $method = $data['method'] ?? 'template';

        if ($method === 'template') {
            abort_if(empty($data['template']), 422, 'No fingerprint captured.');
            abort_if(! $tradesmanId, 422, 'No registry subcontractor is linked to this payment — link one to verify by fingerprint.');
            $result = $this->matchAgainst(\App\Models\Tradesman::class, $tradesmanId, $data['template']);
            abort_unless($result['matched'], 422, ($result['reason'] ?? '') === 'not_enrolled'
                ? 'This subcontractor has no enrolled fingerprint. Enrol one first, or use an allowed fallback.'
                : 'Fingerprint does not match — payment not confirmed.');
        } elseif ($method === 'override') {
            abort_unless($settings->allow_override, 422, 'Manager override is disabled by policy.');
        } elseif ($method === 'pin') {
            abort_unless($settings->allow_pin_fallback, 422, 'PIN fallback is disabled by policy.');
        }

        $payment->update([
            'fingerprint_confirmed' => true,
            'fingerprint_confirmed_at' => now(),
            'fingerprint_method' => $method,
            'verified_by' => $request->user()?->id,
            'verify_note' => $data['note'] ?? null,
        ]);
        ActivityLog::log('updated', 'SubcontractorPayment', "Payment #{$payment->id} verified via {$method}", $payment->project_id);

        return response()->json($payment->fresh());
    }

    // ── helpers ───────────────────────────────────────────────────────────
    private function resolveEnrollable(Request $request): array
    {
        $token = (string) $request->input('enrollable_type');
        abort_unless(isset(self::TYPES[$token]), 422, 'Unknown enrollable type.');

        return [self::TYPES[$token], (int) $request->input('enrollable_id')];
    }

    private function matchAgainst(string $type, int $id, string $candidate): array
    {
        $rows = FingerprintEnrollment::where('enrollable_type', $type)->where('enrollable_id', $id)->get();
        if ($rows->isEmpty()) {
            return ['matched' => false, 'reason' => 'not_enrolled'];
        }
        foreach ($rows as $row) {
            if (hash_equals(trim((string) $row->template), trim($candidate))) {
                return ['matched' => true, 'finger' => $row->finger];
            }
        }

        return ['matched' => false, 'reason' => 'no_match'];
    }
}
