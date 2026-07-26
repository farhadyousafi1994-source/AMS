<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Receipt::with(['invoice:id,invoice_no', 'project:id,name', 'user:id,name'])
            ->orderByDesc('receipt_date')->orderByDesc('id');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);

        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';
        $rate = ($data['currency'] === $base) ? 1 : (float) ($data['rate'] ?? 1);

        $invoice = null;
        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::find($data['invoice_id']);
        }

        $receipt = Receipt::create([
            'invoice_id' => $data['invoice_id'] ?? null,
            'project_id' => $data['project_id'] ?? $invoice?->project_id,
            'user_id' => $request->user()?->id,
            'receipt_no' => $this->nextNumber(),
            'receipt_date' => $data['receipt_date'],
            'payer' => $data['payer'] ?? $invoice?->client_name,
            'method' => $data['method'] ?? null,
            'currency' => $data['currency'],
            'rate' => $rate,
            'amount' => $data['amount'],
            'amount_base' => round(((float) $data['amount']) * $rate, 2),
            'note' => $data['note'] ?? null,
        ]);

        if ($invoice) {
            $this->refreshInvoiceStatus($invoice);
        }

        ActivityLog::log('created', 'Receipt', "Received {$receipt->amount} {$receipt->currency} ({$receipt->receipt_no})");

        return response()->json($receipt->load(['invoice:id,invoice_no', 'user:id,name']), 201);
    }

    public function destroy(Receipt $receipt): JsonResponse
    {
        $invoice = $receipt->invoice;
        $receipt->delete();

        if ($invoice) {
            $this->refreshInvoiceStatus($invoice);
        }

        ActivityLog::log('deleted', 'Receipt', "Deleted receipt {$receipt->receipt_no}");

        return response()->json(['message' => 'Deleted.']);
    }

    private function refreshInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }
        $paid = (float) $invoice->receipts()->sum('amount_base');
        $total = (float) $invoice->total_base;

        if ($paid <= 0) {
            $invoice->update(['status' => 'sent']);
        } elseif ($paid + 0.009 < $total) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'paid']);
        }
    }

    private function nextNumber(): string
    {
        $seq = Receipt::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'RCP-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'receipt_date' => ['required', 'date'],
            'payer' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'in:cash,bank,other'],
            'currency' => ['required', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
