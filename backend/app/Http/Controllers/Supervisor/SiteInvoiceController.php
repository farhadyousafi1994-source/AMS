<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SiteInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The invoice archive: instantly filterable by project, category, source and
 * date. Retrieval is a query — no manual filing. Scoped to assigned projects.
 */
class SiteInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = SiteInvoice::query()
            ->forUser($request->user())
            ->with(['project:id,name,code', 'category:id,name', 'uploader:id,name', 'request:id,code'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->input('project_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->input('to')))
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->get();

        $summary = [
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('actual_total'), 2),
            'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
        ];

        return response()->json(['invoices' => $rows, 'summary' => $summary]);
    }

    public function image(SiteInvoice $siteInvoice): StreamedResponse
    {
        abort_unless($siteInvoice->image_path && Storage::exists($siteInvoice->image_path), 404, 'File not found');

        return Storage::download($siteInvoice->image_path, $siteInvoice->image_name);
    }

    public function destroy(SiteInvoice $siteInvoice): JsonResponse
    {
        $projectId = $siteInvoice->project_id;
        $siteInvoice->delete();

        ActivityLog::log('deleted', 'SiteInvoice', 'Deleted a site invoice from the archive', $projectId);

        return response()->json(['message' => 'Deleted.']);
    }
}
