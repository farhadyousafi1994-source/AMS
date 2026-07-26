<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Subcontractor;
use App\Models\SubcontractorPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcontractorPaymentController extends Controller
{
    public function store(Request $request, Subcontractor $subcontractor): JsonResponse
    {
        $data = $this->rules($request);
        $data['subcontractor_id'] = $subcontractor->id;
        $data['project_id'] = $subcontractor->project_id;
        $data['user_id'] = $request->user()?->id;

        $payment = SubcontractorPayment::create($data);

        ActivityLog::log('created', 'SubPayment', "Recorded {$payment->kind} of {$payment->amount} {$payment->currency} to \"{$subcontractor->name}\"", $subcontractor->project_id);

        return response()->json($payment->load('user:id,name'), 201);
    }

    public function update(Request $request, SubcontractorPayment $payment): JsonResponse
    {
        $payment->update($this->rules($request));

        ActivityLog::log('updated', 'SubPayment', "Updated subcontractor payment #{$payment->id}", $payment->project_id);

        return response()->json($payment->load('user:id,name'));
    }

    public function destroy(SubcontractorPayment $payment): JsonResponse
    {
        $payment->delete();

        ActivityLog::log('deleted', 'SubPayment', "Deleted subcontractor payment #{$payment->id}", $payment->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'payment_date' => ['required', 'date'],
            'kind' => ['required', 'in:payment,advance'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
