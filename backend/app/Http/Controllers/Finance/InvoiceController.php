<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Invoice;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(): JsonResponse
    {
        $invoices = Invoice::with(['project:id,name'])
            ->withSum('receipts as paid_base', 'amount_base')
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->get()
            ->map(function ($inv) {
                $paid = (float) ($inv->paid_base ?? 0);
                $inv->setAttribute('paid_base', round($paid, 2));
                $inv->setAttribute('balance_base', round((float) $inv->total_base - $paid, 2));

                return $inv;
            });

        return response()->json($invoices);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'project:id,name', 'receipts' => fn ($q) => $q->with('user:id,name')->orderByDesc('receipt_date')]);
        $paid = (float) $invoice->receipts->sum('amount_base');
        $invoice->setAttribute('paid_base', round($paid, 2));
        $invoice->setAttribute('balance_base', round((float) $invoice->total_base - $paid, 2));

        return response()->json($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);

        $invoice = DB::transaction(function () use ($data, $request) {
            $inv = Invoice::create($this->headerData($data, $request, true));
            $this->syncItems($inv, $data['items'] ?? []);
            $this->recalcTotals($inv, $data);

            return $inv;
        });

        ActivityLog::log('created', 'Invoice', "Created invoice {$invoice->invoice_no} for {$invoice->client_name}");

        return response()->json($this->fresh($invoice), 201);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $this->rules($request);

        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->update($this->headerData($data, $request, false));
            $this->syncItems($invoice, $data['items'] ?? []);
            $this->recalcTotals($invoice, $data);
            $this->refreshStatus($invoice);
        });

        ActivityLog::log('updated', 'Invoice', "Updated invoice {$invoice->invoice_no}");

        return response()->json($this->fresh($invoice));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $no = $invoice->invoice_no;
        $invoice->delete();

        ActivityLog::log('deleted', 'Invoice', "Deleted invoice {$no}");

        return response()->json(['message' => 'Deleted.']);
    }

    // ── helpers ──

    private function headerData(array $data, Request $request, bool $creating): array
    {
        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';
        $currency = $data['currency'] ?? $base;
        $rate = ($currency === $base) ? 1 : (float) ($data['rate'] ?? 1);

        $header = [
            'project_id' => $data['project_id'] ?? null,
            'client_name' => $data['client_name'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'currency' => $currency,
            'rate' => $rate,
            'status' => $data['status'] ?? 'draft',
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ];

        if ($creating) {
            $header['user_id'] = $request->user()?->id;
            $header['invoice_no'] = $this->nextNumber();
        }

        return $header;
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        foreach ($items as $it) {
            $qty = (float) ($it['qty'] ?? 1);
            $price = (float) ($it['unit_price'] ?? 0);
            $invoice->items()->create([
                'description' => $it['description'] ?? '',
                'qty' => $qty,
                'unit_price' => $price,
                'amount' => round($qty * $price, 2),
            ]);
        }
    }

    private function recalcTotals(Invoice $invoice, array $data): void
    {
        $subtotal = (float) $invoice->items()->sum('amount');
        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'total_base' => round($total * (float) $invoice->rate, 2),
        ]);
    }

    private function refreshStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }
        $paid = (float) $invoice->receipts()->sum('amount_base');
        $total = (float) $invoice->total_base;

        if ($paid <= 0) {
            if (in_array($invoice->status, ['partial', 'paid'])) {
                $invoice->update(['status' => 'sent']);
            }
        } elseif ($paid + 0.009 < $total) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'paid']);
        }
    }

    private function nextNumber(): string
    {
        $seq = Invoice::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'INV-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function fresh(Invoice $invoice): Invoice
    {
        $invoice->load(['items', 'project:id,name']);

        return $invoice;
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,sent,partial,paid,cancelled'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
