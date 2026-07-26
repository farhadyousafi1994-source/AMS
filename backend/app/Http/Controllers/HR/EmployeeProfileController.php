<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEducation;
use App\Models\Project;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One-click full employee profile: information, salary history, attendance,
 * studies, specializations, documents and projects worked on.
 */
class EmployeeProfileController extends Controller
{
    use CompressesImages;

    public function profile(Employee $employee): JsonResponse
    {
        $employee->load([
            'department:id,name', 'designation:id,title', 'manager:id,full_name',
            'assignedVehicle:id,name,serial', 'educations', 'documents.employee:id',
        ]);

        // Salary history from payroll runs.
        $salary = $employee->payrollItems()->with('run:id,period,status,currency')
            ->get()->sortByDesc(fn ($i) => $i->run?->period)->values()
            ->map(fn ($i) => [
                'period' => $i->run?->period,
                'status' => $i->run?->status,
                'currency' => $i->run?->currency,
                'basic' => $i->basic, 'allowances' => $i->allowances,
                'overtime' => $i->overtime, 'deductions' => $i->deductions,
                'absent_days' => $i->absent_days, 'net' => $i->net,
            ]);

        // Attendance summary + recent.
        $att = $employee->attendances()->orderByDesc('att_date')->get();
        $attendance = [
            'present' => $att->where('status', 'present')->count(),
            'absent' => $att->where('status', 'absent')->count(),
            'leave' => $att->where('status', 'leave')->count(),
            'total' => $att->count(),
            'recent' => $att->take(30)->values(),
        ];

        // Projects worked on (from the assigned_projects id list).
        $projects = Project::whereIn('id', $employee->assigned_projects ?? [])
            ->get(['id', 'name', 'code', 'status', 'progress']);

        $employee->setAttribute('salary_history', $salary);
        $employee->setAttribute('attendance', $attendance);
        $employee->setAttribute('projects', $projects);
        $employee->setAttribute('paid_total', round((float) $salary->sum('net'), 2));

        return response()->json($employee);
    }

    // ── Education (studies) ──
    public function addEducation(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'degree' => ['required', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'year_from' => ['nullable', 'string', 'max:9'],
            'year_to' => ['nullable', 'string', 'max:9'],
            'grade' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);
        $data['employee_id'] = $employee->id;
        $edu = EmployeeEducation::create($data);

        return response()->json($edu, 201);
    }

    public function deleteEducation(EmployeeEducation $education): JsonResponse
    {
        $education->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Specializations (skill tags) ──
    public function updateSpecializations(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
        ]);
        $employee->update(['specializations' => $data['specializations'] ?? []]);

        return response()->json(['specializations' => $employee->specializations]);
    }

    // ── Documents (degree, national id, passport, license…) ──
    public function addDocument(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', 'in:degree,national_id,passport,license,contract,certificate,other'],
            'title' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $file = $request->file('file');
        $data['employee_id'] = $employee->id;
        $data['uploaded_by'] = $request->user()->id;
        [$data['file_path'], $docMime] = $this->storeCompressed($file, 'employee-docs/'.Tenant::id().'/'.$employee->id);
        $data['file_name'] = $file->getClientOriginalName();
        $data['file_mime'] = $docMime;
        unset($data['file']);

        $doc = EmployeeDocument::create($data);
        ActivityLog::log('created', 'Employee', "Uploaded {$doc->doc_type} for \"{$employee->full_name}\"");

        return response()->json($doc, 201);
    }

    public function deleteDocument(EmployeeDocument $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function downloadDocument(EmployeeDocument $document): StreamedResponse
    {
        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'File not found');

        return Storage::download($document->file_path, $document->file_name);
    }

    // ── Profile photo ──
    public function uploadPhoto(Request $request, Employee $employee): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        [$photoPath] = $this->storeCompressed($request->file('photo'), 'employee-photos/'.Tenant::id());
        $employee->update(['photo' => $photoPath]);

        return response()->json(['photo' => $employee->photo]);
    }

    public function photo(Employee $employee): StreamedResponse
    {
        abort_unless($employee->photo && Storage::exists($employee->photo), 404, 'No photo');

        return Storage::download($employee->photo);
    }
}
