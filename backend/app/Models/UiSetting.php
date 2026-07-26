<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One Control Room preference: whether a UI element (menu, page, tab, input,
 * table feature) is hidden, its sort order, and an optional label override.
 */
class UiSetting extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'sort_order' => 'integer', 'props' => 'array'];
    }
}
