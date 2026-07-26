<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subcontractor extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Cross-project subcontractor this engagement belongs to. */
    public function tradesman(): BelongsTo
    {
        return $this->belongsTo(Tradesman::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractorPayment::class);
    }
}
