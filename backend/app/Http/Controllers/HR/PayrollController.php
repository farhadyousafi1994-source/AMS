<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PayrollRun::withCount('items')->withSum('items as net_total', 'net')
                ->orderByDesc('period')->get()
        );
    }

    /**
     * Build a month's draft run. Basic + allowances (housing/transport) come from
     * the employee record; present/absent/leave days come from attendance; an
     * unpaid-absence deduction is prefilled as basic/30 per absent day. Every
     * component stays editable until the run is marked paid.
     */
    public function generate(Request $request): JsonResponse
    {
        $period = $request->validate(['period' => ['required', 'date_format:Y-m']])['period'];
        abort_if(PayrollRun::where('period', $period)->exists(), 422, "A run for {$period} already exists.");

        $run = PayrollRun::create(['period' => $period, 'status' => 'draft', 'currency' => 'AFN']);

        // Attendance tallies for the month, grouped by employee + status.
        $tallies = AttendanceRecord::whereBetween('att_date', ["{$period}-01", "{$period}-31"])
            ->get(['employee_id', 'status'])
            ->groupBy('employee_id');

        foreach (Employee::where('status', 'active')->get() as $e) {
            $mine = $tallies[$e->id] ?? collect();
            $absent = $mine->where('status', 'absent')->count();
            $present = $mine->where('status', 'present')->count();
            $leave = $mine->where('status', 'leave')->count();

            $basic = (float) $e->basic_salary;
            $allow = is_array($e->allowances) ? array_sum(array_map('floatval', $e->allowances)) : 0.0;
            $deduction = round($basic / 30 * $absent, 2);

            $item = new PayrollItem([
                'employee_id' => $e->id,
                'basic' => $basic, 'allowances' => $allow,
                'housing' => 0, 'transport' => 0, 'bonus' => 0, 'overtime' => 0,
                'tax' => 0, 'loan' => 0, 'advance' => 0, 'deductions' => $deduction,
                'absent_days' => $absent, 'present_days' => $present, 'leave_days' => $leave,
            ]);
            $this->recompute($item);
            $run->items()->save($item);
        }

        ActivityLog::log('created', 'Payroll', "Generated payroll run {$period}");

        return response()->json($run->load('items.employee:id,code,full_name'), 201);
    }

    /** gross = earnings; net = gross − (absence + tax + loan + advance). */
    private function recompute(PayrollItem $item): void
    {
        $gross = (float) $item->basic + (float) $item->allowances + (float) $item->housing
            + (float) $item->transport + (float) $item->overtime + (float) $item->bonus;
        $totalDeductions = (float) $item->deductions + (float) $item->tax + (float) $item->loan + (float) $item->advance;
        $item->gross = round($gross, 2);
        $item->net = round($gross - $totalDeductions, 2);
    }

    public function show(PayrollRun $payroll): JsonResponse
    {
        return response()->json($payroll->load([
            'items.employee:id,code,full_name,designation_id,department_id,bank_details,payment_method',
            'items.employee.designation:id,title',
            'items.employee.department:id,name',
        ]));
    }

    /** Adjust any component while the run is a draft; net/gross recomputed. */
    public function updateItem(Request $request, PayrollItem $item): JsonResponse
    {
        abort_if($item->run?->status === 'paid', 422, 'A paid run can no longer be edited.');
        $data = $request->validate([
            'basic' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'housing' => ['nullable', 'numeric', 'min:0'],
            'transport' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'overtime' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'loan' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
        ]);
        $item->fill($data);
        $this->recompute($item);
        $item->save();

        return response()->json($item->load('employee:id,code,full_name'));
    }

    public function markPaid(PayrollRun $payroll): JsonResponse
    {
        $payroll->update(['status' => 'paid', 'paid_at' => now()]);
        ActivityLog::log('updated', 'Payroll', "Marked payroll run {$payroll->period} as paid");

        return response()->json($payroll);
    }

    public function destroy(PayrollRun $payroll): JsonResponse
    {
        abort_if($payroll->status === 'paid', 422, 'A paid run cannot be deleted.');
        $payroll->items()->delete();
        $payroll->delete();
        ActivityLog::log('deleted', 'Payroll', "Deleted draft payroll run {$payroll->period}");

        return response()->json(['message' => 'Deleted.']);
    }
}
