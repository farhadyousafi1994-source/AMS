<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An HSE / Safety incident, near-miss, hazard or accident on a project. */
class SafetyIncident extends Model
{
    use BelongsToCompany, HasAttachments, SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['is_open'];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date:Y-m-d',
            'injured_count' => 'integer',
            'lost_time_days' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /** Still requiring attention (anything not yet closed). */
    public function getIsOpenAttribute(): bool
    {
        return $this->status !== 'closed';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
