<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use BelongsToCompany, HasAttachments, SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['available'];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'allocated' => 'integer',
            'purchase_date' => 'date',
            'purchase_value' => 'decimal:2',
        ];
    }

    /** available = total − allocated (never stored directly) */
    public function getAvailableAttribute(): int
    {
        return max(0, (int) $this->quantity_total - (int) $this->allocated);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
