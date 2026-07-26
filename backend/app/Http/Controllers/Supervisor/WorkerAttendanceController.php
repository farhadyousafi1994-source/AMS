<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daily field attendance with photo + GPS. One mark per worker per day. Captures
 * queued offline are replayed through store()/sync() with a client_uuid so a
 * retry never double-marks. Scoped to assigned projects.
 */
class WorkerAttendanceController extends Controller
{
    use CompressesImages;

    public function index(Request $request): JsonResponse
    {
        $rows = WorkerAttendance::query()
            ->forUser($request->user())
            ->with(['worker:id,name,code,trade,photo_path', 'recorder:id,name'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->input('project_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('work_date', $request->input('date')))
            ->when($request->filled('worker_id'), fn ($q) => $q->where('worker_id', $request->input('worker_id')))
            ->orderByDesc('work_date')->orderByDesc('id')
            ->get();

        $present = $rows->where('status', 'present')->count() + $rows->where('status', 'half')->count();

        return response()->json([
            'records' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'present' => $present,
                'absent' => $rows->where('status', 'absent')->count(),
                'wage_total' => round((float) $rows->whereIn('status', ['present', 'half'])->sum(fn ($r) => (float) $r->day_rate * ($r->status === 'half' ? 0.5 : 1)), 2),
                'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
            ],
        ]);
    }

    /** Mark one worker for a day. Upserts on (worker, date) so re-sync is safe. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $record = $this->upsert($request, $data, $request->file('photo'));

        return response()->json($record->load('worker:id,name,code'), 201);
    }

    /** Offline replay: a batch of queued marks (multipart not supported — no photos). */
    public function sync(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'records' => ['required', 'array'],
            'records.*.worker_id' => ['required', 'integer', 'exists:workers,id'],
            'records.*.project_id' => ['required', 'integer', 'exists:projects,id'],
            'records.*.work_date' => ['required', 'date'],
            'records.*.status' => ['nullable', 'in:present,absent,half'],
            'records.*.task' => ['nullable', 'string', 'max:255'],
            'records.*.day_rate' => ['nullable', 'numeric', 'min:0'],
            'records.*.gps_lat' => ['nullable', 'numeric'],
            'records.*.gps_lng' => ['nullable', 'numeric'],
            'records.*.client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $saved = 0;
        foreach ($payload['records'] as $r) {
            $this->upsert($request, $r, null);
            $saved++;
        }

        ActivityLog::log('created', 'WorkerAttendance', "Synced {$saved} offline attendance record(s)");

        return response()->json(['synced' => $saved]);
    }

    public function destroy(WorkerAttendance $workerAttendance): JsonResponse
    {
        $projectId = $workerAttendance->project_id;
        $workerAttendance->delete();

        ActivityLog::log('deleted', 'WorkerAttendance', 'Removed an attendance record', $projectId);

        return response()->json(['message' => 'Deleted.']);
    }

    public function photo(WorkerAttendance $workerAttendance): StreamedResponse
    {
        abort_unless($workerAttendance->photo_path && Storage::exists($workerAttendance->photo_path), 404, 'No photo');

        return Storage::download($workerAttendance->photo_path, $workerAttendance->photo_name);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'work_date' => ['required', 'date'],
            'status' => ['nullable', 'in:present,absent,half'],
            'task' => ['nullable', 'string', 'max:255'],
            'day_rate' => ['nullable', 'numeric', 'min:0'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    /** Prefill the day rate from the worker when none was sent. */
    private function upsert(Request $request, array $data, $photo): WorkerAttendance
    {
        $attrs = [
            'status' => $data['status'] ?? 'present',
            'task' => $data['task'] ?? null,
            'day_rate' => $data['day_rate'] ?? (float) (Worker::withoutGlobalScopes()->find($data['worker_id'])?->default_wage ?? 0),
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
            'recorded_by' => $request->user()->id,
            'signed_at' => now(),
        ];

        if ($photo) {
            [$attrs['photo_path'], $photoMime] = $this->storeCompressed($photo, 'worker-attendance/'.Tenant::id().'/'.$data['project_id']);
            $attrs['photo_name'] = $photo->getClientOriginalName();
            $attrs['photo_mime'] = $photoMime;
        }

        // Match on the date part only — the column stores a datetime, so a plain
        // updateOrCreate on 'Y-m-d' would miss and trip the unique index on retry.
        $date = \Illuminate\Support\Carbon::parse($data['work_date'])->toDateString();
        $record = WorkerAttendance::where('worker_id', $data['worker_id'])->whereDate('work_date', $date)->first();

        if ($record) {
            $record->update($attrs);

            return $record;
        }

        return WorkerAttendance::create($attrs + ['worker_id' => $data['worker_id'], 'work_date' => $date, 'project_id' => $data['project_id']]);
    }
}
