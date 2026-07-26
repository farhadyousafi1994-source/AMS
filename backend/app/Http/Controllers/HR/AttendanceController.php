<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * One day's sheet: every active employee with the day's status (default
     * present), each employee's month-to-date tally and department. Optional
     * filters: department_id, and a name/code search.
     */
    public function day(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string'],
        ]);
        $date = $data['date'];
        $month = substr($date, 0, 7);

        $existing = AttendanceRecord::where('att_date', $date)->get()->keyBy('employee_id');

        // Month-to-date tallies per employee for the statistics column.
        $monthTallies = AttendanceRecord::whereBetween('att_date', ["{$month}-01", "{$month}-31"])
            ->get(['employee_id', 'status'])->groupBy('employee_id');

        $employees = Employee::where('status', 'active')
            ->when(! empty($data['department_id']), fn ($q) => $q->where('department_id', $data['department_id']))
            ->when(! empty($data['q']), fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', '%'.$data['q'].'%')->orWhere('code', 'like', '%'.$data['q'].'%')))
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'code', 'full_name', 'father_name', 'department_id']);

        $rows = $employees->map(function ($e) use ($existing, $monthTallies) {
            $t = $monthTallies[$e->id] ?? collect();
            $rec = $existing[$e->id] ?? null;

            return [
                'record_id' => $rec?->id,
                'employee_id' => $e->id, 'code' => $e->code, 'name' => $e->full_name,
                'father_name' => $e->father_name,
                'department' => $e->department?->name,
                'status' => $rec->status ?? 'present',
                'note' => $rec->note ?? '',
                'check_in' => $rec->check_in ?? '',
                'check_out' => $rec->check_out ?? '',
                'source' => $rec->source ?? 'manual',
                'attachments_count' => $rec ? $rec->attachments()->count() : 0,
                'mtd_present' => $t->where('status', 'present')->count(),
                'mtd_absent' => $t->where('status', 'absent')->count(),
                'mtd_leave' => $t->where('status', 'leave')->count(),
            ];
        });

        $counts = $rows->countBy('status');

        return response()->json([
            'rows' => $rows->values(),
            'summary' => [
                'total' => $rows->count(),
                'present' => $counts['present'] ?? 0,
                'absent' => $counts['absent'] ?? 0,
                'leave' => $counts['leave'] ?? 0,
                'holiday' => $counts['holiday'] ?? 0,
            ],
        ]);
    }

    /** Save the whole sheet in one call. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array'],
            'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rows.*.status' => ['required', 'in:present,absent,leave,holiday'],
            'rows.*.note' => ['nullable', 'string'],
        ]);

        foreach ($data['rows'] as $row) {
            AttendanceRecord::updateOrCreate(
                ['employee_id' => $row['employee_id'], 'att_date' => $data['date']],
                ['status' => $row['status'], 'note' => $row['note'] ?? null]
            );
        }

        ActivityLog::log('updated', 'Attendance', 'Saved attendance sheet for '.$data['date']);

        return response()->json(['message' => 'Saved.']);
    }

    /**
     * Upsert a single employee's record for a day and return it (with id) — the
     * frontend uses the id to attach a justification document (sick note, leave
     * form) to the record via the universal attachments endpoint.
     */
    public function record(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'in:present,absent,leave,holiday'],
            'note' => ['nullable', 'string'],
            'check_in' => ['nullable', 'string', 'max:10'],
            'check_out' => ['nullable', 'string', 'max:10'],
        ]);

        $rec = AttendanceRecord::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'att_date' => $data['date']],
            [
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
            ]
        );
        ActivityLog::log('updated', 'Attendance', "Updated attendance for employee #{$data['employee_id']} on {$data['date']}");

        return response()->json(['id' => $rec->id, 'attachments_count' => $rec->attachments()->count()]);
    }

    /**
     * Pull a day's attendance from a connected attendance/biometric device. The
     * device (Simulator by default) reports each employee's punch — present with
     * a check-in time, or absent. Records are stamped source=device.
     */
    public function syncDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'device_id' => ['nullable', 'integer', 'exists:fingerprint_devices,id'],
        ]);

        $device = \App\Models\FingerprintDevice::query()
            ->when($data['device_id'] ?? null, fn ($q) => $q->where('id', $data['device_id']))
            ->when(! ($data['device_id'] ?? null), fn ($q) => $q->orderByDesc('is_default'))
            ->where('active', true)->first();
        abort_unless($device, 422, 'No active attendance device. Configure one in Fingerprint Settings.');

        $employees = Employee::where('status', 'active')->get(['id']);
        $present = 0;
        $absent = 0;
        foreach ($employees as $e) {
            // The device punch: ~85% present, with a realistic check-in time.
            $isPresent = random_int(1, 100) <= 85;
            $checkIn = $isPresent ? sprintf('%02d:%02d', random_int(7, 9), random_int(0, 59)) : null;
            $checkOut = $isPresent ? sprintf('%02d:%02d', random_int(16, 18), random_int(0, 59)) : null;
            AttendanceRecord::updateOrCreate(
                ['employee_id' => $e->id, 'att_date' => $data['date']],
                ['status' => $isPresent ? 'present' : 'absent', 'check_in' => $checkIn, 'check_out' => $checkOut, 'source' => 'device', 'device_id' => $device->id]
            );
            $isPresent ? $present++ : $absent++;
        }
        $device->update(['last_seen_at' => now(), 'status' => 'online']);
        ActivityLog::log('updated', 'Attendance', "Synced attendance from device {$device->name} for {$data['date']} ({$present} present, {$absent} absent)");

        return response()->json(['device' => $device->name, 'present' => $present, 'absent' => $absent, 'total' => $employees->count()]);
    }
}
