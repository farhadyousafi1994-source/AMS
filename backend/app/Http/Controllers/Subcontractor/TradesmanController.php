<?php

namespace App\Http\Controllers\Subcontractor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Subcontractor;
use App\Models\SubcontractorPayment;
use App\Models\Tradesman;
use App\Models\WorkMeasurement;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cross-project subcontractors (استادکاران): one person, many projects. Weekly
 * payments and work measurements roll up across every engagement. Fingerprint
 * lookup answers "how much have I taken so far?" with a date-stamped history.
 */
class TradesmanController extends Controller
{
    use CompressesImages;

    public function index(): JsonResponse
    {
        $rows = Tradesman::query()
            ->withCount('engagements as projects_count')
            ->withCount('ratings as rating_count')
            ->withAvg('ratings as rating_avg', 'stars')
            ->with('engagements:id,tradesman_id,project_id,contract_amount')
            ->orderByDesc('id')->get()
            ->map(function ($t) {
                $t->setAttribute('rating_avg', $t->rating_avg ? round((float) $t->rating_avg, 1) : null);
                $paid = (float) SubcontractorPayment::whereIn('subcontractor_id', $t->engagements->pluck('id'))->where('kind', 'payment')->sum('amount');
                $advance = (float) SubcontractorPayment::whereIn('subcontractor_id', $t->engagements->pluck('id'))->where('kind', 'advance')->sum('amount');
                $contract = (float) $t->engagements->sum('contract_amount');
                $t->setAttribute('contract_total', round($contract, 2));
                $t->setAttribute('paid_total', round($paid + $advance, 2));
                $t->setAttribute('balance', round($contract - ($paid + $advance), 2));
                $t->unsetRelation('engagements');

                return $t;
            });

        $summary = [
            'count' => $rows->count(),
            'active' => $rows->where('active', true)->count(),
            'contract_total' => round((float) $rows->sum('contract_total'), 2),
            'paid_total' => round((float) $rows->sum('paid_total'), 2),
            'balance_total' => round((float) $rows->sum('balance'), 2),
            'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
        ];

        return response()->json(['tradesmen' => $rows, 'summary' => $summary]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = 'SUB-'.str_pad((string) (Tradesman::withTrashed()->where('company_id', Tenant::id())->count() + 1), 4, '0', STR_PAD_LEFT);
        $this->attachPhoto($request, $data);

        $t = Tradesman::create($data);

        ActivityLog::log('created', 'Tradesman', "Registered subcontractor {$t->code} — {$t->name}");

        return response()->json($t, 201);
    }

    public function show(Tradesman $tradesman): JsonResponse
    {
        return response()->json($this->profile($tradesman));
    }

    public function update(Request $request, Tradesman $tradesman): JsonResponse
    {
        $data = $this->rules($request);
        $this->attachPhoto($request, $data);
        $tradesman->update($data);

        ActivityLog::log('updated', 'Tradesman', "Updated subcontractor {$tradesman->code}");

        return response()->json($this->profile($tradesman));
    }

    public function destroy(Tradesman $tradesman): JsonResponse
    {
        $code = $tradesman->code;
        $tradesman->delete();

        ActivityLog::log('deleted', 'Tradesman', "Deleted subcontractor {$code}");

        return response()->json(['message' => 'Deleted.']);
    }

    public function photo(Tradesman $tradesman): StreamedResponse
    {
        abort_unless($tradesman->photo_path && Storage::exists($tradesman->photo_path), 404, 'No photo');

        return Storage::download($tradesman->photo_path, $tradesman->photo_name);
    }

    /** Fingerprint payout query: scan → full date-stamped history across projects. */
    public function fingerprint(Request $request): JsonResponse
    {
        $fp = (string) $request->input('fingerprint_id', $request->route('fingerprint'));
        $tradesman = Tradesman::where('fingerprint_id', $fp)->first();

        abort_unless($tradesman, 404, 'No subcontractor matches this fingerprint.');

        return response()->json($this->profile($tradesman));
    }

    /** Add a per-project engagement (contract) for this tradesman. */
    public function addEngagement(Request $request, Tradesman $tradesman): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'trade' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string'],
            'contract_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);
        $data['tradesman_id'] = $tradesman->id;
        $data['name'] = $tradesman->name;

        $eng = Subcontractor::create($data);
        ActivityLog::log('created', 'Tradesman', "Engaged {$tradesman->name} on a project", $eng->project_id);

        return response()->json($this->profile($tradesman), 201);
    }

    /** Record a weekly payment (or advance) against a project engagement. */
    public function addPayment(Request $request, Tradesman $tradesman): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'payment_date' => ['required', 'date'],
            'kind' => ['nullable', 'in:payment,advance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string'],
        ]);

        $engagement = $tradesman->engagements()->where('project_id', $data['project_id'])->first();
        abort_unless($engagement, 422, 'Engage this subcontractor on the project first.');

        SubcontractorPayment::create([
            'subcontractor_id' => $engagement->id,
            'project_id' => $data['project_id'],
            'user_id' => $request->user()->id,
            'payment_date' => $data['payment_date'],
            'kind' => $data['kind'] ?? 'payment',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'AFN',
            'note' => $data['note'] ?? null,
        ]);

        ActivityLog::log('created', 'Tradesman', "Paid {$data['amount']} to {$tradesman->name}", $data['project_id']);

        return response()->json($this->profile($tradesman), 201);
    }

    /**
     * Verify a payment against the subcontractor's fingerprint — proof of
     * receipt. The scanned id (from the virtual scanner or a hardware device)
     * must match the registered fingerprint id.
     */
    public function confirmPaymentFingerprint(Request $request, SubcontractorPayment $payment): JsonResponse
    {
        $scanned = $request->validate(['fingerprint_id' => ['required', 'string', 'max:100']])['fingerprint_id'];

        $engagement = \App\Models\Subcontractor::find($payment->subcontractor_id);
        $tradesman = $engagement?->tradesman;
        abort_unless($tradesman, 422, 'No subcontractor is linked to this payment.');
        abort_unless($tradesman->fingerprint_id, 422, 'This subcontractor has no fingerprint registered. Register one first.');
        abort_unless(hash_equals((string) $tradesman->fingerprint_id, (string) trim($scanned)), 422, 'Fingerprint does not match — payment not confirmed.');

        $payment->update(['fingerprint_confirmed' => true, 'fingerprint_confirmed_at' => now()]);
        ActivityLog::log('updated', 'Tradesman', "Fingerprint-confirmed a payment to \"{$tradesman->name}\"", $payment->project_id);

        return response()->json($this->profile($tradesman));
    }

    public function addMeasurement(Request $request, Tradesman $tradesman): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'measure_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);
        $data['tradesman_id'] = $tradesman->id;
        $data['amount'] = round($data['quantity'] * $data['unit_price'], 2);
        $data['recorded_by'] = $request->user()->id;

        WorkMeasurement::create($data);
        ActivityLog::log('created', 'Tradesman', "Measured work for {$tradesman->name}", $data['project_id']);

        return response()->json($this->profile($tradesman), 201);
    }

    public function deleteMeasurement(WorkMeasurement $measurement): JsonResponse
    {
        $measurement->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Immutable per-project performance rating (1–5). No edit endpoint by design. */
    public function addRating(Request $request, Tradesman $tradesman): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'timeliness' => ['nullable', 'integer', 'min:1', 'max:5'],
            'safety' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'rated_by_name' => ['nullable', 'string', 'max:255'],
        ]);
        $data['tradesman_id'] = $tradesman->id;
        $data['rated_by'] = $request->user()->id;

        \App\Models\TradesmanRating::create($data);
        ActivityLog::log('created', 'Tradesman', "Rated {$tradesman->name} ({$data['stars']}★)", $data['project_id']);

        return response()->json($this->profile($tradesman), 201);
    }

    // ── helpers ──
    private function profile(Tradesman $t): Tradesman
    {
        $t->load([
            'engagements.project:id,name,code',
            'engagements.payments' => fn ($q) => $q->orderByDesc('payment_date'),
            'measurements.project:id,name,code',
            'ratings.project:id,name,code',
        ]);
        $t->setRelation('ratings', $t->ratings->sortByDesc('created_at')->values());

        // Flatten every payment across projects into one date-stamped ledger.
        $payments = $t->engagements->flatMap(function ($e) {
            return $e->payments->map(function ($p) use ($e) {
                $p->setAttribute('project_name', $e->project?->name);
                $p->setAttribute('project_code', $e->project?->code);

                return $p;
            });
        })->sortByDesc('payment_date')->values();

        $paid = (float) $payments->where('kind', 'payment')->sum('amount');
        $advance = (float) $payments->where('kind', 'advance')->sum('amount');
        $contract = (float) $t->engagements->sum('contract_amount');
        $measured = (float) $t->measurements->sum('amount');

        $ratingCount = $t->ratings->count();
        $t->setAttribute('all_payments', $payments);
        $t->setAttribute('summary', [
            'projects' => $t->engagements->count(),
            'contract_total' => round($contract, 2),
            'paid_total' => round($paid, 2),
            'advance_total' => round($advance, 2),
            'balance' => round($contract - ($paid + $advance), 2),
            'measured_total' => round($measured, 2),
            'rating_avg' => $ratingCount ? round((float) $t->ratings->avg('stars'), 1) : null,
            'rating_count' => $ratingCount,
            'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
        ]);

        return $t;
    }

    private function attachPhoto(Request $request, array &$data): void
    {
        if ($file = $request->file('photo')) {
            [$data['photo_path'], $photoMime] = $this->storeCompressed($file, 'tradesmen/'.Tenant::id());
            $data['photo_name'] = $file->getClientOriginalName();
            $data['photo_mime'] = $photoMime;
        }
        unset($data['photo']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'trade' => ['nullable', 'string', 'max:100'],
            'cnic' => ['nullable', 'string', 'max:100'],
            'fingerprint_id' => ['nullable', 'string', 'max:100'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'rate_unit' => ['nullable', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }
}
