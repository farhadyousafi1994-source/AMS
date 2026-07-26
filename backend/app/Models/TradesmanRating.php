<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One immutable performance rating per project for a subcontractor. */
class TradesmanRating extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    public function tradesman(): BelongsTo
    {
        return $this->belongsTo(Tradesman::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
