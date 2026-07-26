<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

class FingerprintSetting extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_override' => 'boolean',
            'allow_pin_fallback' => 'boolean',
            'fallback_when_unavailable' => 'boolean',
            'min_quality' => 'integer',
        ];
    }

    /** The single settings row for the current company (created with defaults). */
    public static function current(): self
    {
        return static::firstOrCreate(['company_id' => Tenant::id()]);
    }
}
