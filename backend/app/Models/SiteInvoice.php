<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\ScopedToAssignedProjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field invoice/receipt — the archive entry. The phone photo captured at the
 * shop IS the record; metadata (project, category, vendor, amount, request) is
 * inherited so filing is automatic. Named apart from the client-billing Invoice.
 */
class SiteInvoice extends Model
{
    use BelongsToCompany, HasAttachments, ScopedToAssignedProjects, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['actual_total' => 'decimal:2', 'invoice_date' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PurchaseCategory::class, 'category_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
