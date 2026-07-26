<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $leaves = Leave::with(['employee:id,code,full_name', 'approver:id,name'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('month'), function ($q) use ($request) {
                $m = $request->string('month'); // YYYY-MM
                $q->where('start_date', '<=', "{$m}-31")->where('end_date', '>=', "{$m}-01");
            })
            ->orderByDesc('start_date')
            ->get();

        return response()->json($leaves);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'type' => ['required', 'in:annual,sick,unpaid,maternity,other'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'paid' => ['boolean'],
            'reason' => ['nullable', 'string'],
        ]);

        $data['days'] = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        $data['status'] = 'pending';

        $leave = Leave::create($data);

        ActivityLog::log('created', 'Leave', "Leave request for employee #{$leave->employee_id} ({$data['days']} days)");

        return response()->json($leave->load('employee:id,code,full_name'), 201);
    }

    public function decide(Request $request, Leave $leave): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);

        $leave->update([
            'status' => $data['status'],
            'approved_by' => $request->user()?->id,
        ]);

        // Approving a leave marks those days on the attendance sheet as 'leave'.
        if ($data['status'] === 'approved') {
            $cursor = $leave->start_date->copy();
            while ($cursor->lte($leave->end_date)) {
                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $leave->employee_id, 'att_date' => $cursor->toDateString()],
                    ['status' => 'leave', 'note' => 'Leave: '.$leave->type]
                );
                $cursor->addDay();
            }
        }

        ActivityLog::log('updated', 'Leave', "Leave #{$leave->id} {$data['status']}");

        return response()->json($leave->load('employee:id,code,full_name', 'approver:id,name'));
    }

    public function destroy(Leave $leave): JsonResponse
    {
        $leave->delete();
        ActivityLog::log('deleted', 'Leave', "Deleted leave #{$leave->id}");

        return response()->json(['message' => 'Deleted.']);
    }
}
