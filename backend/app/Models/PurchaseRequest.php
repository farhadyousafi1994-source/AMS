<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\ScopedToAssignedProjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use BelongsToCompany, HasAttachments, ScopedToAssignedProjects, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'estimated_total' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PurchaseCategory::class, 'category_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(CashAdvance::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SiteInvoice::class);
    }

    /** Money released vs. money proven spent, for the reconciliation view. */
    public function advancedTotal(): float
    {
        return (float) $this->advances()->sum('amount_given');
    }

    public function spentTotal(): float
    {
        return (float) $this->invoices()->sum('actual_total');
    }
}
