<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Aggregations for the Office and Home expense dashboards: totals, breakdowns
 * by category / vendor / method, monthly & yearly series, partner shares
 * (office) and budget-vs-actual (home). Approved rows only for money totals.
 */
class ExpenseInsightsController extends Controller
{
    public function office(): JsonResponse
    {
        return response()->json($this->build('office') + ['partners' => $this->partnerShares()]);
    }

    public function home(): JsonResponse
    {
        return response()->json($this->build('home') + ['budget' => $this->budgetVsActual('home')]);
    }

    private function build(string $type): array
    {
        $rows = Expense::where('type', $type)->get();
        $approved = $rows->where('approval_status', 'approved');
        $now = now();

        $sumBase = fn ($c) => round((float) $c->sum('amount_base'), 2);
        $byGroup = fn ($c, $key) => $c->groupBy(fn ($e) => $e->{$key} ?: '—')
            ->map(fn ($g, $k) => ['name' => $k, 'total' => round((float) $g->sum('amount_base'), 2), 'count' => $g->count()])
            ->sortByDesc('total')->values();

        // Monthly series (last 12 months).
        $monthly = collect(range(11, 0))->map(function ($back) use ($approved, $now) {
            $m = $now->copy()->subMonths($back);
            $period = $m->format('Y-m');
            $total = $approved->filter(fn ($e) => Carbon::parse($e->expense_date)->format('Y-m') === $period)->sum('amount_base');

            return ['period' => $period, 'total' => round((float) $total, 2)];
        })->values();

        $yearly = $approved->groupBy(fn ($e) => Carbon::parse($e->expense_date)->format('Y'))
            ->map(fn ($g, $y) => ['year' => $y, 'total' => round((float) $g->sum('amount_base'), 2)])
            ->sortBy('year')->values();

        $thisMonth = $approved->filter(fn ($e) => Carbon::parse($e->expense_date)->format('Y-m') === $now->format('Y-m'));
        $thisYear = $approved->filter(fn ($e) => Carbon::parse($e->expense_date)->format('Y') === $now->format('Y'));

        return [
            'type' => $type,
            'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
            'summary' => [
                'count' => $rows->count(),
                'pending' => $rows->where('approval_status', 'pending')->count(),
                'this_month' => $sumBase($thisMonth),
                'this_year' => $sumBase($thisYear),
                'all_time' => $sumBase($approved),
            ],
            'by_category' => $byGroup($approved, 'category'),
            'by_vendor' => $byGroup($approved, 'vendor'),
            'by_method' => $byGroup($approved, 'payment_method'),
            'monthly' => $monthly,
            'yearly' => $yearly,
            'recent' => $rows->sortByDesc('expense_date')->take(10)->values()
                ->map(fn ($e) => $e->only(['id', 'expense_date', 'category', 'vendor', 'payee', 'payment_method', 'amount', 'currency', 'amount_base', 'approval_status'])),
        ];
    }

    /** Equal-share split of this year's approved office overhead among partners. */
    private function partnerShares(): array
    {
        $partners = Partner::where('active', true)->orderBy('id')->get();
        $yearTotal = (float) Expense::where('type', 'office')->where('approval_status', 'approved')
            ->whereYear('expense_date', now()->year)->sum('amount_base');

        return [
            'year_total' => round($yearTotal, 2),
            'rows' => $partners->map(fn ($p) => [
                'name' => $p->name,
                'share_percent' => (float) $p->share_percent,
                'share_amount' => round($yearTotal * (float) $p->share_percent / 100, 2),
            ])->values(),
        ];
    }

    /** Home budget vs actual for the last 12 months. */
    private function budgetVsActual(string $type): array
    {
        $now = now();
        $rows = collect(range(11, 0))->map(function ($back) use ($type, $now) {
            $period = $now->copy()->subMonths($back)->format('Y-m');
            $budget = (float) ExpenseBudget::where('type', $type)->whereNull('category')->where('period', $period)->sum('amount');
            $actual = (float) Expense::where('type', $type)->where('approval_status', 'approved')
                ->whereRaw("strftime('%Y-%m', expense_date) = ?", [$period])->sum('amount_base');

            return ['period' => $period, 'budget' => round($budget, 2), 'actual' => round($actual, 2), 'variance' => round($budget - $actual, 2)];
        })->values();

        $current = $rows->firstWhere('period', $now->format('Y-m')) ?? ['budget' => 0, 'actual' => 0, 'variance' => 0];

        return ['series' => $rows, 'current' => $current];
    }
}
