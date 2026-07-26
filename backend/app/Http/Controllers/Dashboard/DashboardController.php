<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Construction-domain dashboard fed by live module data: projects,
 * receipts/invoices, the General Budget treasury, HR and the asset pool.
 */
class DashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $companyId = Tenant::id();
        // Per-project scoping: null = all projects (admin/President/all-projects).
        $pids = $request->user()->visibleProjectIds();
        $scoped = fn ($q, $col = 'project_id') => $q->when($pids !== null, fn ($qq) => $qq->whereIn($col, $pids));

        $totalBranches = Branch::where('company_id', $companyId)->count();

        $activeProjects = $scoped(Project::where('company_id', $companyId)->where('status', 'active'), 'id')->count();
        $contractValueTotal = $scoped(Project::where('company_id', $companyId)
            ->whereIn('status', ['planning', 'active', 'on_hold']), 'id')->sum('contract_value');

        $recentActivity = $scoped(ActivityLog::with('user')->where('company_id', $companyId))
            ->latest()->limit(10)
            ->get(['id', 'user_id', 'action', 'module', 'description', 'created_at', 'project_id']);

        // Finance figures — scoped to the user's projects when restricted.
        $collectedMonth = (float) $scoped(\App\Models\Receipt::whereBetween('receipt_date', [now()->startOfMonth(), now()->endOfMonth()]))->sum('amount_base');
        $invoiced = (float) $scoped(\App\Models\Invoice::query())->sum('total_base');
        $collectedAll = (float) $scoped(\App\Models\Receipt::query())->sum('amount_base');

        return response()->json([
            // Projects & Sites — live
            'active_projects'      => $activeProjects,
            'contract_value_total' => $contractValueTotal,

            // Finance — live
            'collected_month'      => round($collectedMonth, 2),
            'outstanding_balance'  => round(max(0, $invoiced - $collectedAll), 2),
            'treasury'             => \App\Models\TreasuryTransaction::summary(),

            // HR / Procurement — subcontractors scoped by project when restricted.
            'total_employees'      => \App\Models\Employee::where('status', 'active')->count(),
            'total_suppliers'      => $scoped(\App\Models\Subcontractor::query())->count(),
            'total_equipment'      => \App\Models\Asset::count(),
            'sees_all_projects'    => $pids === null,

            // Real, already-working platform data
            'total_branches'  => $totalBranches,
            'recent_activity' => $recentActivity,
        ]);
    }
}
