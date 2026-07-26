<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department:id,name', 'designation:id,title'])
            ->orderByDesc('id');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->integer('designation_id'));
        }
        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->string('employment_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->get());
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'department:id,name', 'designation:id,title',
            'manager:id,full_name', 'assignedVehicle:id,name,serial',
        ]);

        return response()->json($employee);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $this->nextCode();

        $employee = Employee::create($data);

        ActivityLog::log('created', 'Employee', "Added employee \"{$employee->full_name}\" ({$employee->code})");

        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $employee->update($this->rules($request, $employee));

        ActivityLog::log('updated', 'Employee', "Updated employee \"{$employee->full_name}\"");

        return response()->json($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $name = $employee->full_name;
        $employee->delete();

        ActivityLog::log('deleted', 'Employee', "Deleted employee \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function nextCode(): string
    {
        $seq = Employee::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'EMP-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            // Personal
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'grandfather_name' => ['nullable', 'string', 'max:255'],
            'tazkira' => ['nullable', 'string', 'max:100', Rule::unique('employees', 'tazkira')->ignore($employee?->id)->whereNull('deleted_at')],
            'gender' => ['nullable', 'in:male,female'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'marital_status' => ['nullable', 'in:single,married'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            // Employment
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'employment_type' => ['required', 'in:permanent,contract,daily_wage'],
            'join_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['nullable', 'in:active,on_leave,inactive'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'assigned_vehicle_id' => ['nullable', 'integer', 'exists:assets,id'],
            'license' => ['nullable', 'string', 'max:255'],
            'assigned_projects' => ['nullable', 'array'],
            'assigned_projects.*' => ['integer'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            // Payroll
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'in:cash,bank,hawala'],
            'bank_details' => ['nullable', 'string', 'max:255'],
            'allowances' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
