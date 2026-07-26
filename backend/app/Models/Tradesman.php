<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cross-project subcontractor (استادکار). Works across many projects; each
 * per-project engagement is a Subcontractor row linked back here.
 */
class Tradesman extends Model
{
    use BelongsToCompany, HasAttachments, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
        ];
    }

    /** Per-project engagements. */
    public function engagements(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(WorkMeasurement::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TradesmanRating::class);
    }

    /** All payments across every project this tradesman worked on. */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(SubcontractorPayment::class, Subcontractor::class);
    }
}
