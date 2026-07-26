<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A stored biometric template (encrypted at rest — never a raw image). */
class FingerprintEnrollment extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['template']; // never expose the template blob to the client

    protected function casts(): array
    {
        return [
            'template' => 'encrypted', // Laravel encrypts/decrypts transparently
            'quality' => 'integer',
        ];
    }

    public function enrollable(): MorphTo
    {
        return $this->morphTo();
    }
}
