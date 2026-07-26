<?php

namespace App\Http\Controllers;

use App\Models\ChangeOrder;
use App\Models\Employee;
use App\Models\Party;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Models\Tradesman;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System-wide search. Only queries entities the user may list, and respects
 * per-project scoping for project-bound records. Returns grouped, navigable hits.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $pids = $user->visibleProjectIds();
        $like = '%'.$q.'%';
        $groups = [];

        $add = function (string $type, string $icon, $rows) use (&$groups) {
            if ($rows->isNotEmpty()) {
                $groups[] = ['type' => $type, 'icon' => $icon, 'items' => $rows->values()];
            }
        };

        if ($user->can('project-list')) {
            $add('Projects', 'domain', Project::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('client_name', 'like', $like))
                ->when($pids !== null, fn ($x) => $x->whereIn('id', $pids))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->name, 'sub' => trim(($r->code ?? '').' · '.$r->status), 'to' => '/projects/'.$r->id]));
        }

        if ($user->can('employee-list')) {
            $add('Employees', 'badge', Employee::query()
                ->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('tazkira', 'like', $like))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->full_name, 'sub' => trim(($r->code ?? '').' · '.($r->phone ?? '')), 'to' => '/hr/employees/'.$r->id]));
        }

        if ($user->can('tradesman-list')) {
            $add('Subcontractors', 'engineering', Tradesman::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('trade', 'like', $like))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->name, 'sub' => trim(($r->code ?? '').' · '.($r->trade ?? '')), 'to' => '/subcontractors/'.$r->id]));
        }

        if ($user->can('worker-list')) {
            $add('Workers', 'groups', Worker::query()->forUser($user)
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('father_name', 'like', $like))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->name, 'sub' => trim(($r->code ?? '').' · '.($r->trade ?? '')), 'to' => '/site/workers']));
        }

        if ($user->can('party-list')) {
            $add('Accounts', 'account_balance', Party::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->name, 'sub' => $r->code ?? '', 'to' => '/accounts']));
        }

        if ($user->can('supplier-list')) {
            $add('Suppliers', 'local_shipping', Supplier::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->name, 'sub' => $r->code ?? '', 'to' => '/procurement/suppliers']));
        }

        if ($user->can('change-order-list')) {
            $add('ChangeOrders', 'published_with_changes', ChangeOrder::query()
                ->with('project:id,name')
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('code', 'like', $like))
                ->when($pids !== null, fn ($x) => $x->whereIn('project_id', $pids))
                ->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->title, 'sub' => trim(($r->code ?? '').' · '.($r->project?->name ?? '')), 'to' => '/change-orders']));
        }

        return response()->json(['groups' => $groups]);
    }
}
