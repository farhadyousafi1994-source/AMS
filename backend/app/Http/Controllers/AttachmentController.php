<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use CompressesImages;

    /**
     * Whitelist of attachable types. Keeps the polymorphic endpoint from being
     * pointed at arbitrary models. alias => Eloquent model class.
     *
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'project' => \App\Models\Project::class,
        'purchase-request' => \App\Models\PurchaseRequest::class,
        'worker-attendance' => \App\Models\WorkerAttendance::class,
        'attendance-record' => \App\Models\AttendanceRecord::class,
        'employee' => \App\Models\Employee::class,
        'worker' => \App\Models\Worker::class,
        'user' => \App\Models\User::class,
        'expense' => \App\Models\Expense::class,
        'invoice' => \App\Models\Invoice::class,
        'site-invoice' => \App\Models\SiteInvoice::class,
        'subcontractor-payment' => \App\Models\SubcontractorPayment::class,
        'tradesman' => \App\Models\Tradesman::class,
        // Finance & procurement
        'purchase-order' => \App\Models\PurchaseOrder::class,
        'receipt' => \App\Models\Receipt::class,
        'supplier' => \App\Models\Supplier::class,
        'contract' => \App\Models\Contract::class,
        'contract-payment' => \App\Models\ContractPayment::class,
        'stock-item' => \App\Models\StockItem::class,
        'party' => \App\Models\Party::class,
        'change-order' => \App\Models\ChangeOrder::class,
        'asset' => \App\Models\Asset::class,
        'safety-incident' => \App\Models\SafetyIncident::class,
        'payment-request' => \App\Models\PaymentRequest::class,
        'treasury' => \App\Models\TreasuryTransaction::class,
    ];

    /** Types whose documents belong in the financial Invoice Archive. */
    private const FINANCIAL_TYPES = [
        'expense', 'invoice', 'receipt', 'site-invoice', 'purchase-request',
        'purchase-order', 'contract-payment', 'subcontractor-payment',
        'payment-request', 'treasury',
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'kind' => ['nullable', 'string'],
        ]);

        $parent = $this->resolveParent($data['type'], (int) $data['id']);

        $attachments = $parent->attachments()
            ->when(! empty($data['kind']), fn ($q) => $q->where('kind', $data['kind']))
            ->with('uploader:id,name')
            ->get()
            ->map(fn (Attachment $a) => $this->present($a));

        return response()->json($attachments);
    }

    /**
     * The Invoice Archive: every document attached to any financial record
     * (expenses, invoices, receipts, purchases, payments, treasury) in one list.
     */
    public function archive(Request $request): JsonResponse
    {
        $classFor = array_intersect_key(self::TYPES, array_flip(self::FINANCIAL_TYPES));
        $aliasByClass = array_flip($classFor);

        $rows = Attachment::query()
            ->where('company_id', Tenant::id())
            ->whereIn('attachable_type', array_values($classFor))
            ->with(['uploader:id,name', 'attachable'])
            ->latest()
            ->limit(500)
            ->get()
            ->map(function (Attachment $a) use ($aliasByClass) {
                $out = $this->present($a);
                $p = $a->attachable;
                $out['source_type'] = $aliasByClass[$a->attachable_type] ?? $a->attachable_type;
                $out['source_id'] = $a->attachable_id;
                $out['source_label'] = $p?->code ?? $p?->number ?? $p?->title ?? $p?->name
                    ?? $p?->payee ?? $p?->payee_name ?? $p?->vendor ?? $p?->category
                    ?? ('#'.$a->attachable_id);

                return $out;
            });

        // Office/home expense receipts are stored on the expense row itself —
        // surface them here too so the archive really is everything.
        $embedded = \App\Models\Expense::query()
            ->whereNotNull('attachment_path')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn ($e) => [
                'id' => null,
                'kind' => 'receipt',
                'original_name' => $e->attachment_name ?: basename((string) $e->attachment_path),
                'mime' => $e->attachment_mime,
                'size' => null,
                'caption' => $e->description,
                'is_image' => str_starts_with((string) $e->attachment_mime, 'image/'),
                'url' => "/api/expenses/{$e->id}/attachment",
                'uploaded_by' => null,
                'created_at' => $e->created_at,
                'source_type' => 'expense',
                'source_id' => $e->id,
                'source_label' => $e->payee ?: ($e->category ?: 'Expense'),
            ]);

        $all = $rows->concat($embedded)->sortByDesc('created_at')->values();

        return response()->json($all);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'kind' => ['nullable', 'string', 'max:40'],
            'caption' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:25600'], // 25 MB pre-compression
        ]);

        $parent = $this->resolveParent($data['type'], (int) $data['id']);
        $kind = $data['kind'] ?? 'file';

        [$path, $mime, $size] = $this->storeFile(
            $request->file('file'),
            'attachments/'.Tenant::id().'/'.$data['type'].'/'.$parent->getKey()
        );

        // Avatars are 1:1 — replace any previous profile photo.
        if ($kind === 'avatar') {
            foreach ($parent->attachments()->where('kind', 'avatar')->get() as $old) {
                Storage::disk($old->disk)->delete($old->path);
                $old->forceDelete();
            }
        }

        $attachment = $parent->attachments()->create([
            'company_id' => Tenant::id(),
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime' => $mime,
            'size' => $size,
            'caption' => $data['caption'] ?? null,
            'uploaded_by' => $request->user()?->id,
        ]);

        ActivityLog::log('created', 'Attachment', "Attached \"{$attachment->original_name}\" to {$data['type']} #{$parent->getKey()}");

        return response()->json($this->present($attachment->load('uploader:id,name')), 201);
    }

    public function view(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime ?: 'application/octet-stream']
        );
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $name = $attachment->original_name;
        $attachment->delete(); // soft delete; file retained for restore

        ActivityLog::log('deleted', 'Attachment', "Removed attachment \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    /** Resolve + authorize the parent record (company scope enforced by its global scope). */
    private function resolveParent(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 422, 'Unsupported attachment type.');

        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];

        return $class::findOrFail($id);
    }

    private function present(Attachment $a): array
    {
        return [
            'id' => $a->id,
            'kind' => $a->kind,
            'original_name' => $a->original_name,
            'mime' => $a->mime,
            'size' => $a->size,
            'caption' => $a->caption,
            'is_image' => $a->is_image,
            'url' => "/api/attachments/{$a->id}/view",
            'uploaded_by' => $a->uploader?->name,
            'created_at' => $a->created_at,
        ];
    }

    /**
     * Store a file, transparently downscaling + recompressing large images so
     * a 6 MB phone photo lands as a lean web-sized JPEG. Returns [path, mime, size].
     *
     * @return array{0:string,1:string,2:int}
     */
    private function storeFile(UploadedFile $file, string $dir): array
    {
        return $this->storeCompressed($file, $dir);
    }

}
