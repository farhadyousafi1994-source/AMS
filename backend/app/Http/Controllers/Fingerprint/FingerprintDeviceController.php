<?php

namespace App\Http\Controllers\Fingerprint;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FingerprintDevice;
use App\Services\Fingerprint\FingerprintManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FingerprintDeviceController extends Controller
{
    public function __construct(private FingerprintManager $manager) {}

    public function index(): JsonResponse
    {
        return response()->json(
            FingerprintDevice::orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        if (! empty($data['is_default'])) {
            FingerprintDevice::query()->update(['is_default' => false]);
        }
        $device = FingerprintDevice::create($data);
        ActivityLog::log('created', 'FingerprintDevice', "Registered device {$device->name} ({$device->brand})");

        return response()->json($device, 201);
    }

    public function update(Request $request, FingerprintDevice $fingerprintDevice): JsonResponse
    {
        $data = $this->rules($request);
        if (! empty($data['is_default'])) {
            FingerprintDevice::where('id', '!=', $fingerprintDevice->id)->update(['is_default' => false]);
        }
        $fingerprintDevice->update($data);
        ActivityLog::log('updated', 'FingerprintDevice', "Updated device {$fingerprintDevice->name}");

        return response()->json($fingerprintDevice);
    }

    public function destroy(FingerprintDevice $fingerprintDevice): JsonResponse
    {
        $fingerprintDevice->delete();
        ActivityLog::log('deleted', 'FingerprintDevice', "Removed device {$fingerprintDevice->name}");

        return response()->json(['message' => 'Deleted.']);
    }

    /** Auto-detect reachable devices across every registered brand driver. */
    public function detect(): JsonResponse
    {
        $found = [];
        foreach (FingerprintManager::registry() as $brand => $class) {
            foreach ((new $class)->detect() as $d) {
                $found[] = $d;
            }
        }

        return response()->json($found);
    }

    /** Live connectivity test for one device. */
    public function test(FingerprintDevice $fingerprintDevice): JsonResponse
    {
        $result = $this->manager->forDevice($fingerprintDevice)->test($fingerprintDevice);
        $fingerprintDevice->update([
            'status' => $result['online'] ? 'online' : 'offline',
            'last_seen_at' => $result['online'] ? now() : $fingerprintDevice->last_seen_at,
        ]);

        return response()->json($result);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:40'],
            'model' => ['nullable', 'string', 'max:120'],
            'connection' => ['required', 'string', 'max:30'],
            'host' => ['nullable', 'string', 'max:120'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'serial' => ['nullable', 'string', 'max:120'],
            'settings' => ['nullable', 'array'],
            'active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);
    }
}
