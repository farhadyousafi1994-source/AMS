<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    use CompressesImages;

    public function index(Project $project): JsonResponse
    {
        return response()->json(
            ProjectDocument::where('project_id', $project->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:drawing,contract,permit,photo,report,other'],
            'version' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        [$path, $docMime] = $this->storeCompressed($file, 'documents/'.Tenant::id().'/'.$project->id);

        $doc = ProjectDocument::create([
            'project_id' => $project->id,
            'user_id' => $request->user()?->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'other',
            'version' => $data['version'] ?? 1,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $docMime,
            'size' => $file->getSize(),
            'notes' => $data['notes'] ?? null,
        ]);

        ActivityLog::log('created', 'Document', "Uploaded \"{$doc->title}\" (v{$doc->version}) to project \"{$project->name}\"", $project->id);

        return response()->json($doc->load('user:id,name'), 201);
    }

    public function update(Request $request, ProjectDocument $document): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'in:drawing,contract,permit,photo,report,other'],
            'version' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $document->update($data);

        ActivityLog::log('updated', 'Document', "Updated document \"{$document->title}\"", $document->project_id);

        return response()->json($document->load('user:id,name'));
    }

    public function destroy(ProjectDocument $document): JsonResponse
    {
        $title = $document->title;
        // Soft-delete the record; the underlying file is kept so a future
        // restore keeps working. Force-deletes can prune files later.
        $document->delete();

        ActivityLog::log('deleted', 'Document', "Deleted document \"{$title}\"", $document->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    public function download(ProjectDocument $document): StreamedResponse
    {
        abort_unless(Storage::exists($document->file_path), 404, 'File not found');

        return Storage::download($document->file_path, $document->file_name);
    }
}
