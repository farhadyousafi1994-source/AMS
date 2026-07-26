<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectInvestment;
use App\Models\Receipt;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A single report engine. Every report resolves to a common shape
 * { title, columns, rows, summary } so one frontend renderer + the shared
 * PDF / Excel / Word exporters can present all of them. Filters (date range,
 * project, currency) are shared; each report applies the ones it understands.
 */
class ReportController extends Controller
{
    public function generate(Request $request, string $type): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $report = match ($type) {
            'executive' => $this->executive($filters),
            'pnl' => $this->pnl($filters),
            'project' => $this->project($filters),
            'captable' => $this->capTable($filters),
            'stock' => $this->stock($filters),
            'payroll' => $this->payroll($filters),
            'approval-log' => $this->approvalLog($filters),
            'treasury' => $this->treasury($filters),
            'accounts' => $this->partyAccounts($filters),
            default => null,
        };

        abort_if($report === null, 404, 'Unknown report type.');

        return response()->json($report);
    }

    // ── Executive overview ──
    private function executive(array $f): array
    {
        $projects = Project::query();
        $receipts = $this->dateScope(Receipt::query(), 'receipt_date', $f)->sum('amount_base');
        $expenses = $this->dateScope(Expense::query(), 'expense_date', $f)->sum('amount_base');
        $raised = (float) ProjectInvestment::query()->get()->sum(fn ($r) => (float) $r->capital * (float) $r->rate);

        $rows = [
            ['metric' => 'Total Projects', 'value' => (string) $projects->count(), '_link' => '/projects'],
            ['metric' => 'Active Projects', 'value' => (string) (clone $projects)->where('status', 'active')->count(), '_link' => '/projects'],
            ['metric' => 'Contracted Value (base)', 'value' => $this->money($projects->sum('contract_value')), '_link' => '/projects'],
            ['metric' => 'Capital Raised (base)', 'value' => $this->money($raised), '_link' => '/investors'],
            ['metric' => 'Receipts (base)', 'value' => $this->money($receipts), '_link' => '/finance/receipts'],
            ['metric' => 'Expenses (base)', 'value' => $this->money($expenses), '_link' => '/finance/expenses'],
            ['metric' => 'Net Cash (base)', 'value' => $this->money($receipts - $expenses), '_link' => '/finance/treasury'],
            ['metric' => 'Employees', 'value' => (string) Employee::count(), '_link' => '/hr/employees'],
            ['metric' => 'Assets', 'value' => (string) Asset::count(), '_link' => '/assets'],
            ['metric' => 'Contracts', 'value' => (string) Contract::count(), '_link' => '/contracts'],
        ];

        return $this->shape('Executive Overview', [
            ['name' => 'metric', 'label' => 'Metric', 'align' => 'left'],
            ['name' => 'value', 'label' => 'Value', 'align' => 'right'],
        ], $rows, [
            ['label' => 'Net Cash (base)', 'value' => $this->money($receipts - $expenses)],
        ]);
    }

    // ── Profit & Loss ──
    private function pnl(array $f): array
    {
        $receipts = (float) $this->dateScope(Receipt::query(), 'receipt_date', $f)->sum('amount_base');
        $invoiced = (float) $this->dateScope(Invoice::query(), 'invoice_date', $f)->sum('total_base');

        $byCategory = $this->dateScope(Expense::query(), 'expense_date', $f)
            ->selectRaw('category, SUM(amount_base) as total')
            ->groupBy('category')->orderByDesc('total')->get();
        $expenseTotal = (float) $byCategory->sum('total');

        $rows = [
            ['line' => 'Income — Receipts', 'amount' => $this->money($receipts), 'kind' => 'income', '_link' => '/finance/receipts'],
            ['line' => 'Income — Invoiced (accrual)', 'amount' => $this->money($invoiced), 'kind' => 'income', '_link' => '/finance/invoices'],
        ];
        foreach ($byCategory as $c) {
            $rows[] = ['line' => 'Expense — '.($c->category ?: 'Uncategorised'), 'amount' => $this->money($c->total), 'kind' => 'expense', '_link' => '/finance/expenses'];
        }
        $rows[] = ['line' => 'Net Profit (cash basis)', 'amount' => $this->money($receipts - $expenseTotal), 'kind' => 'net', '_link' => '/finance/treasury'];

        return $this->shape('Profit & Loss', [
            ['name' => 'line', 'label' => 'Line', 'align' => 'left'],
            ['name' => 'amount', 'label' => 'Amount (base)', 'align' => 'right'],
        ], $rows, [
            ['label' => 'Total Income', 'value' => $this->money($receipts)],
            ['label' => 'Total Expenses', 'value' => $this->money($expenseTotal)],
            ['label' => 'Net Profit', 'value' => $this->money($receipts - $expenseTotal)],
        ]);
    }

    // ── Full project report ──
    private function project(array $f): array
    {
        $id = $f['project_id'] ?? null;
        $project = $id ? Project::with(['milestones', 'sites', 'subcontractors', 'investments'])->find($id) : null;
        if (! $project) {
            return $this->shape('Project Report', [
                ['name' => 'field', 'label' => 'Field', 'align' => 'left'],
                ['name' => 'value', 'label' => 'Value', 'align' => 'left'],
            ], [['field' => 'Notice', 'value' => 'Select a project to run this report.']], []);
        }

        $raised = (float) $project->investments->sum(fn ($r) => (float) $r->capital * (float) $r->rate);
        $link = '/projects/'.$project->id;
        $rows = [
            ['field' => 'Code', 'value' => (string) $project->code, '_link' => $link],
            ['field' => 'Name', 'value' => (string) $project->name, '_link' => $link],
            ['field' => 'Client', 'value' => (string) $project->client_name, '_link' => $link],
            ['field' => 'Location', 'value' => (string) $project->location, '_link' => $link],
            ['field' => 'Status', 'value' => (string) $project->status, '_link' => $link],
            ['field' => 'Physical Progress', 'value' => $project->progress.'%', '_link' => $link],
            ['field' => 'Contract Value', 'value' => $this->money($project->contract_value).' '.$project->currency, '_link' => $link],
            ['field' => 'Capital Raised (base)', 'value' => $this->money($raised), '_link' => $link],
            ['field' => 'Funding Gap (base)', 'value' => $this->money(max(0, (float) $project->contract_value - $raised)), '_link' => $link],
            ['field' => 'Sites', 'value' => (string) $project->sites->count(), '_link' => $link],
            ['field' => 'Milestones', 'value' => $project->milestones->where('status', 'done')->count().' / '.$project->milestones->count().' done', '_link' => $link],
            ['field' => 'Subcontractors', 'value' => (string) $project->subcontractors->count(), '_link' => $link],
            ['field' => 'Cap-table Participants', 'value' => (string) $project->investments->count(), '_link' => $link],
        ];

        return $this->shape('Project Report — '.$project->name, [
            ['name' => 'field', 'label' => 'Field', 'align' => 'left'],
            ['name' => 'value', 'label' => 'Value', 'align' => 'left'],
        ], $rows, [
            ['label' => 'Progress', 'value' => $project->progress.'%'],
            ['label' => 'Capital Raised (base)', 'value' => $this->money($raised)],
        ]);
    }

    // ── Cap table + per-investor statement (first-class) ──
    private function capTable(array $f): array
    {
        $query = ProjectInvestment::with(['project:id,name', 'investor:id,name']);
        if (! empty($f['project_id'])) {
            $query->where('project_id', $f['project_id']);
        }
        $items = $query->get();

        $rows = $items->map(fn ($r) => [
            'participant' => $r->participant_name,
            'project' => $r->project?->name ?? '—',
            'capital' => $this->money((float) $r->capital * (float) $r->rate),
            'profit_percent' => (float) $r->profit_percent.'%',
            'profit_received' => $this->money($r->profit_received),
            '_link' => $r->project_id ? '/projects/'.$r->project_id : '/investors',
        ])->values();

        $totalCapital = (float) $items->sum(fn ($r) => (float) $r->capital * (float) $r->rate);
        $totalProfit = (float) $items->sum('profit_received');

        return $this->shape('Cap Table & Investor Statement', [
            ['name' => 'participant', 'label' => 'Participant', 'align' => 'left'],
            ['name' => 'project', 'label' => 'Project', 'align' => 'left'],
            ['name' => 'capital', 'label' => 'Capital (base)', 'align' => 'right'],
            ['name' => 'profit_percent', 'label' => 'Profit %', 'align' => 'center'],
            ['name' => 'profit_received', 'label' => 'Profit Received', 'align' => 'right'],
        ], $rows->all(), [
            ['label' => 'Total Capital (base)', 'value' => $this->money($totalCapital)],
            ['label' => 'Total Profit Paid', 'value' => $this->money($totalProfit)],
        ]);
    }

    // ── Stock / asset allocation ──
    private function stock(array $f): array
    {
        $assets = Asset::orderBy('category')->orderBy('name')->get();
        $rows = $assets->map(fn ($a) => [
            'code' => (string) $a->code,
            'name' => $a->name,
            'category' => (string) $a->category,
            'total' => (string) $a->quantity_total,
            'allocated' => (string) $a->allocated,
            'available' => (string) max(0, (int) $a->quantity_total - (int) $a->allocated),
            '_link' => '/assets',
        ])->values();

        return $this->shape('Stock & Asset Allocation', [
            ['name' => 'code', 'label' => 'Code', 'align' => 'left'],
            ['name' => 'name', 'label' => 'Name', 'align' => 'left'],
            ['name' => 'category', 'label' => 'Category', 'align' => 'left'],
            ['name' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['name' => 'allocated', 'label' => 'Allocated', 'align' => 'right'],
            ['name' => 'available', 'label' => 'Available', 'align' => 'right'],
        ], $rows->all(), [
            ['label' => 'Asset Lines', 'value' => (string) $assets->count()],
        ]);
    }

    // ── Payroll ──
    private function payroll(array $f): array
    {
        $employees = Employee::with(['department:id,name', 'designation:id,title'])
            ->where('status', 'active')->orderBy('full_name')->get();

        $grand = 0.0;
        $rows = $employees->map(function ($e) use (&$grand) {
            $allow = is_array($e->allowances) ? array_sum(array_map('floatval', $e->allowances)) : 0.0;
            $gross = (float) $e->basic_salary + $allow;
            $grand += $gross;

            return [
                'code' => (string) $e->code,
                'name' => $e->full_name,
                'department' => $e->department?->name ?? '—',
                'basic' => $this->money($e->basic_salary),
                'allowances' => $this->money($allow),
                'gross' => $this->money($gross),
                'currency' => (string) $e->salary_currency,
                '_link' => '/hr/employees/'.$e->id,
            ];
        })->values();

        return $this->shape('Payroll Sheet', [
            ['name' => 'code', 'label' => 'Code', 'align' => 'left'],
            ['name' => 'name', 'label' => 'Employee', 'align' => 'left'],
            ['name' => 'department', 'label' => 'Department', 'align' => 'left'],
            ['name' => 'basic', 'label' => 'Basic', 'align' => 'right'],
            ['name' => 'allowances', 'label' => 'Allowances', 'align' => 'right'],
            ['name' => 'gross', 'label' => 'Gross', 'align' => 'right'],
            ['name' => 'currency', 'label' => 'Currency', 'align' => 'center'],
        ], $rows->all(), [
            ['label' => 'Headcount', 'value' => (string) $employees->count()],
            ['label' => 'Gross Payroll', 'value' => $this->money($grand)],
        ]);
    }

    // ── Approval / activity log ──
    private function approvalLog(array $f): array
    {
        $logs = $this->dateScope(
            ActivityLog::with('user:id,name')->where('company_id', Tenant::id()),
            'created_at', $f
        )->latest()->limit(500)->get();

        $rows = $logs->map(fn ($l) => [
            'when' => optional($l->created_at)->format('Y-m-d H:i'),
            'user' => $l->user?->name ?? '—',
            'action' => (string) $l->action,
            'module' => (string) $l->module,
            'description' => (string) $l->description,
            '_link' => $this->moduleLink((string) $l->module),
        ])->values();

        return $this->shape('Approval & Activity Log', [
            ['name' => 'when', 'label' => 'When', 'align' => 'left'],
            ['name' => 'user', 'label' => 'User', 'align' => 'left'],
            ['name' => 'action', 'label' => 'Action', 'align' => 'left'],
            ['name' => 'module', 'label' => 'Module', 'align' => 'left'],
            ['name' => 'description', 'label' => 'Description', 'align' => 'left'],
        ], $rows->all(), [
            ['label' => 'Entries', 'value' => (string) $logs->count()],
        ]);
    }

    // ── General Budget (treasury ledger) ──
    private function treasury(array $f): array
    {
        $summary = \App\Models\TreasuryTransaction::summary();
        $rows = $this->dateScope(
            \App\Models\TreasuryTransaction::with('project:id,name'), 'tx_date', $f
        )->orderByDesc('tx_date')->orderByDesc('id')->get()->map(fn ($t) => [
            'date' => optional($t->tx_date)->format('Y-m-d'),
            'kind' => (string) $t->kind,
            'project' => $t->project?->name ?? '—',
            'in' => $t->direction === 'in' ? $this->money($t->amount).' '.$t->currency : '',
            'out' => $t->direction === 'out' ? $this->money($t->amount).' '.$t->currency : '',
            'status' => (string) $t->status,
            'note' => (string) $t->note,
            '_link' => $t->project_id ? '/projects/'.$t->project_id : '/finance/treasury',
        ])->values();

        return $this->shape('General Budget — Ledger', [
            ['name' => 'date', 'label' => 'Date', 'align' => 'left'],
            ['name' => 'kind', 'label' => 'Kind', 'align' => 'left'],
            ['name' => 'project', 'label' => 'Project', 'align' => 'left'],
            ['name' => 'in', 'label' => 'Money In', 'align' => 'right'],
            ['name' => 'out', 'label' => 'Money Out', 'align' => 'right'],
            ['name' => 'status', 'label' => 'Status', 'align' => 'center'],
            ['name' => 'note', 'label' => 'Note', 'align' => 'left'],
        ], $rows->all(), [
            ['label' => 'Available ('.$summary['base'].')', 'value' => $this->money($summary['available'])],
            ['label' => 'Reserved ('.$summary['base'].')', 'value' => $this->money($summary['reserved'])],
            ['label' => 'Total ('.$summary['base'].')', 'value' => $this->money($summary['total'])],
        ]);
    }

    // ── Party accounts (credit / debit balances) ──
    private function partyAccounts(array $f): array
    {
        $parties = \App\Models\Party::query()
            ->withSum(['transactions as in_total' => fn ($q) => $q->where('direction', 'in')->where('status', 'confirmed')], 'amount_base')
            ->withSum(['transactions as out_total' => fn ($q) => $q->where('direction', 'out')->where('status', 'confirmed')], 'amount_base')
            ->orderBy('name')->get();

        $weOwe = 0.0;
        $theyOwe = 0.0;
        $rows = $parties->map(function ($p) use (&$weOwe, &$theyOwe) {
            $balance = (float) ($p->in_total ?? 0) - (float) ($p->out_total ?? 0);
            $balance > 0 ? $weOwe += $balance : $theyOwe += abs($balance);

            return [
                'code' => (string) $p->code,
                'name' => $p->name,
                'type' => (string) $p->type,
                'received' => $this->money($p->in_total),
                'paid' => $this->money($p->out_total),
                'balance' => $this->money(abs($balance)).($balance > 0 ? ' CR' : ($balance < 0 ? ' DR' : '')),
                '_link' => '/accounts',
            ];
        })->values();

        return $this->shape('Party Accounts — Credit / Debit', [
            ['name' => 'code', 'label' => 'Code', 'align' => 'left'],
            ['name' => 'name', 'label' => 'Party', 'align' => 'left'],
            ['name' => 'type', 'label' => 'Type', 'align' => 'left'],
            ['name' => 'received', 'label' => 'Received (base)', 'align' => 'right'],
            ['name' => 'paid', 'label' => 'Paid (base)', 'align' => 'right'],
            ['name' => 'balance', 'label' => 'Balance', 'align' => 'right'],
        ], $rows->all(), [
            ['label' => 'We Owe (Credit)', 'value' => $this->money($weOwe)],
            ['label' => 'They Owe Us (Debit)', 'value' => $this->money($theyOwe)],
            ['label' => 'Parties', 'value' => (string) $parties->count()],
        ]);
    }

    /** Where a log entry's module lives in the app, for report click-through. */
    private function moduleLink(string $module): ?string
    {
        $m = strtolower(trim($module));

        return match (true) {
            str_contains($m, 'project') => '/projects',
            str_contains($m, 'receipt') => '/finance/receipts',
            str_contains($m, 'invoice') => '/finance/invoices',
            str_contains($m, 'expense') => '/finance/expenses',
            str_contains($m, 'treasury') => '/finance/treasury',
            str_contains($m, 'payment') => '/finance/payment-center',
            str_contains($m, 'employee'), str_contains($m, 'hr') => '/hr/employees',
            str_contains($m, 'attendance') => '/hr/attendance',
            str_contains($m, 'payroll') => '/hr/payroll',
            str_contains($m, 'leave') => '/hr/leaves',
            str_contains($m, 'asset') => '/assets',
            str_contains($m, 'stock'), str_contains($m, 'warehouse') => '/procurement/stock',
            str_contains($m, 'purchase order'), str_contains($m, 'purchase-order') => '/procurement/purchase-orders',
            str_contains($m, 'purchase') => '/site/purchases',
            str_contains($m, 'supplier') => '/procurement/suppliers',
            str_contains($m, 'contract') => '/contracts',
            str_contains($m, 'investor'), str_contains($m, 'investment') => '/investors',
            str_contains($m, 'safety'), str_contains($m, 'incident') => '/safety/incidents',
            str_contains($m, 'user') => '/users',
            str_contains($m, 'role') => '/roles',
            default => '/log',
        };
    }

    /** Apply the shared date-range filter to a query on the given column. */
    private function dateScope($query, string $column, array $f)
    {
        if (! empty($f['date_from'])) {
            $query->whereDate($column, '>=', $f['date_from']);
        }
        if (! empty($f['date_to'])) {
            $query->whereDate($column, '<=', $f['date_to']);
        }

        return $query;
    }

    private function money($v): string
    {
        return number_format((float) $v, 2);
    }

    private function shape(string $title, array $columns, array $rows, array $summary): array
    {
        return compact('title', 'columns', 'rows', 'summary');
    }
}
