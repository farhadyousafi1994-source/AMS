<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToBranch, BelongsToCompany, HasAttachments, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'original_contract_value' => 'decimal:2',
            'progress' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The original contract sum is fixed at creation; contract_value is the
        // revised sum (original + approved change orders).
        static::creating(function ($project) {
            if (empty($project->original_contract_value)) {
                $project->original_contract_value = $project->contract_value;
            }
        });
    }

    /** Site team: supervisors/engineers assigned to this project. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('site_role')->withTimestamps();
    }

    public function sites(): HasMany
    {
        return $this->hasMany(ProjectSite::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailySiteLog::class);
    }

    public function subcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(ProjectInvestment::class);
    }

    public function projectAssets(): HasMany
    {
        return $this->hasMany(ProjectAsset::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    /**
     * Progress is driven by the system, not typed in by hand: it is the
     * average progress of the work-breakdown tasks. The moment real work
     * begins on the ground — a task leaves "todo", or a daily site log is
     * filed — a project still in "planning" is promoted to "active".
     */
    public function syncProgress(): void
    {
        $tasks = $this->tasks()->get();
        $progress = $tasks->isNotEmpty()
            ? (int) round($tasks->avg('progress'))
            : (int) $this->progress;

        $started = $progress > 0
            || $tasks->contains(fn ($t) => $t->status !== 'todo')
            || $this->dailyLogs()->exists();

        $status = ($started && $this->status === 'planning') ? 'active' : $this->status;

        if ($progress !== (int) $this->progress || $status !== $this->status) {
            $this->forceFill(['progress' => $progress, 'status' => $status])->saveQuietly();
        }
    }
}
