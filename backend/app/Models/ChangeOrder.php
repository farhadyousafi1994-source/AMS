<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A Change Order (Variation Order) against a project's contract. */
class ChangeOrder extends Model
{
    use BelongsToCompany, HasAttachments, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cost_impact' => 'decimal:2',
            'rate' => 'decimal:4',
            'cost_impact_base' => 'decimal:2',
            'time_impact_days' => 'integer',
            'co_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /** Signed base-currency impact: additions add, deductions subtract. */
    public function signedImpact(): float
    {
        $sign = $this->kind === 'deduction' ? -1 : 1;

        return $this->kind === 'no_cost' ? 0 : $sign * (float) $this->cost_impact_base;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
