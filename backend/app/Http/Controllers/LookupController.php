<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Lookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Options Registry API. Serves every dropdown group (bilingual) and lets admins
 * add/edit/remove options from one place.
 */
class LookupController extends Controller
{
    /**
     * Without ?group: all groups keyed by name (for the frontend cache).
     * With ?group=unit: just that group's options.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lookup::query()
            ->when($request->filled('group'), fn ($q) => $q->where('group', $request->string('group')))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))
            ->orderBy('group')->orderBy('sort_order')->orderBy('label_en');

        $rows = $query->get(['id', 'group', 'code', 'label_en', 'label_fa', 'sort_order', 'active', 'is_system']);

        if ($request->filled('group')) {
            return response()->json($rows->values());
        }

        return response()->json($rows->groupBy('group')->map(fn ($g) => $g->values()));
    }

    /** Distinct group names present in the registry (for the admin page). */
    public function groups(): JsonResponse
    {
        return response()->json(
            Lookup::query()->select('group')->distinct()->orderBy('group')->pluck('group')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $data['code'] ?? Str::slug($data['label_en'], '_');

        $lookup = Lookup::updateOrCreate(
            ['company_id' => \App\Support\Tenant::id(), 'group' => $data['group'], 'code' => $data['code']],
            $data + ['active' => $data['active'] ?? true, 'is_system' => false]
        );
        ActivityLog::log('created', 'Lookup', "Added option {$lookup->label_en} to {$lookup->group}");

        return response()->json($lookup, 201);
    }

    public function update(Request $request, Lookup $lookup): JsonResponse
    {
        $data = $this->rules($request, $lookup);
        // The code is the stable machine value — do not let edits change it.
        unset($data['code'], $data['group']);
        $lookup->update($data);
        ActivityLog::log('updated', 'Lookup', "Updated option {$lookup->label_en} in {$lookup->group}");

        return response()->json($lookup->fresh());
    }

    public function destroy(Lookup $lookup): JsonResponse
    {
        abort_if($lookup->is_system, 422, 'System options cannot be deleted — you can disable it instead.');
        $lookup->delete();
        ActivityLog::log('deleted', 'Lookup', "Removed option {$lookup->label_en} from {$lookup->group}");

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request, ?Lookup $lookup = null): array
    {
        return $request->validate([
            'group' => ['required', 'string', 'max:60'],
            'code' => ['nullable', 'string', 'max:60'],
            'label_en' => ['required', 'string', 'max:120'],
            'label_fa' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
