<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\UiSetting;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Control Room API. Stores, per company, which UI elements are hidden and how
 * menus are ordered. The frontend owns the catalogue of keys; this just
 * persists the overrides and serves them back as a map for fast lookup.
 */
class UiSettingController extends Controller
{
    /** All overrides for the company, keyed for O(1) frontend lookup. */
    public function index(): JsonResponse
    {
        $rows = UiSetting::query()->get(['key', 'hidden', 'sort_order', 'label_override', 'props']);

        return response()->json(
            $rows->keyBy('key')->map(fn ($r) => [
                'hidden' => (bool) $r->hidden,
                'sort_order' => $r->sort_order,
                'label_override' => $r->label_override,
                'props' => $r->props ?: (object) [],
            ])
        );
    }

    /**
     * Upsert a batch of overrides in one shot (the Control Room saves the whole
     * screen at once). Payload: { settings: [ {key, hidden?, sort_order?, label_override?}, … ] }.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:160'],
            'settings.*.hidden' => ['nullable', 'boolean'],
            'settings.*.sort_order' => ['nullable', 'integer'],
            'settings.*.label_override' => ['nullable', 'string', 'max:190'],
            'settings.*.props' => ['nullable', 'array'],
        ]);

        $companyId = Tenant::id();
        foreach ($data['settings'] as $s) {
            UiSetting::updateOrCreate(
                ['company_id' => $companyId, 'key' => $s['key']],
                [
                    'hidden' => $s['hidden'] ?? false,
                    'sort_order' => $s['sort_order'] ?? null,
                    'label_override' => $s['label_override'] ?? null,
                    'props' => $s['props'] ?? null,
                ]
            );
        }
        ActivityLog::log('updated', 'ControlRoom', 'Updated interface visibility ('.count($data['settings']).' items)');

        return $this->index();
    }

    /** Wipe every override — restore the whole interface to defaults. */
    public function reset(): JsonResponse
    {
        UiSetting::query()->delete();
        ActivityLog::log('deleted', 'ControlRoom', 'Reset interface to defaults');

        return response()->json([]);
    }
}
