<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Worker;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Worker registry (anti-ghost-worker). Every laborer is registered once with a
 * photo + generated code; field attendance references registered workers only,
 * so phantom laborers can't be slipped in invisibly. Scoped to assigned projects.
 */
class WorkerController extends Controller
{
    use CompressesImages;

    public function index(Request $request): JsonResponse
    {
        $rows = Worker::query()
            ->forUser($request->user())
            ->with(['project:id,name,code'])
            ->withCount('attendances')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->input('project_id')))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('active', true))
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'trade' => ['nullable', 'string', 'max:100'],
            'default_wage' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $data['registered_by'] = $request->user()->id;
        $data['code'] = $this->nextCode();

        if ($file = $request->file('photo')) {
            [$data['photo_path'], $photoMime] = $this->storeCompressed($file, 'workers/'.Tenant::id().'/'.$data['project_id']);
            $data['photo_name'] = $file->getClientOriginalName();
            $data['photo_mime'] = $photoMime;
        }
        unset($data['photo']);

        $worker = Worker::create($data);

        ActivityLog::log('created', 'Worker', "Registered worker {$worker->code} — {$worker->name}", $worker->project_id);

        return response()->json($worker->load('project:id,name,code'), 201);
    }

    public function update(Request $request, Worker $worker): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'trade' => ['nullable', 'string', 'max:100'],
            'default_wage' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ]);
        $worker->update($data);

        ActivityLog::log('updated', 'Worker', "Updated worker {$worker->code}", $worker->project_id);

        return response()->json($worker);
    }

    public function destroy(Worker $worker): JsonResponse
    {
        $code = $worker->code;
        $projectId = $worker->project_id;
        $worker->delete();

        ActivityLog::log('deleted', 'Worker', "Deleted worker {$code}", $projectId);

        return response()->json(['message' => 'Deleted.']);
    }

    public function photo(Worker $worker): StreamedResponse
    {
        abort_unless($worker->photo_path && Storage::exists($worker->photo_path), 404, 'No photo');

        return Storage::download($worker->photo_path, $worker->photo_name);
    }

    private function nextCode(): string
    {
        $n = Worker::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'WKR-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
