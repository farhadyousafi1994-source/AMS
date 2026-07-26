<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRequest extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'fingerprint_verified' => 'boolean',
            'current_level' => 'integer',
            'needed_by' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PaymentApproval::class)->orderBy('level');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Amount in base currency (for thresholds and consolidated totals). */
    public function baseAmount(): float
    {
        return round((float) $this->requested_amount * (float) ($this->rate ?: 1), 2);
    }
}
