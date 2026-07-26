<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investor extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    public function investments(): HasMany
    {
        return $this->hasMany(ProjectInvestment::class);
    }
}
