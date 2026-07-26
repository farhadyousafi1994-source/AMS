<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\HasAttachments;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, HasAttachments;

    protected $fillable = [
        'name', 'email', 'password', 'company_id',
        'current_company', 'current_branch', 'setting', 'type', 'active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'setting' => 'array',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    /** Projects this user is assigned to (supervisor/engineer scoping). */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('site_role')->withTimestamps();
    }

    /** @return array<int> */
    public function assignedProjectIds(): array
    {
        return $this->projects()->pluck('projects.id')->all();
    }

    /**
     * The immutable Platform Owner (VIP Root) identity. Bound to a single email
     * in config, never to a role or permission — so no tenant "Full Access"
     * role can ever grant platform authority. Enforced server-side only.
     */
    public function isPlatformOwner(): bool
    {
        $owner = (string) config('platform.owner_email');

        return $owner !== '' && $this->email !== null && strcasecmp($this->email, $owner) === 0;
    }

    /** Admins, the President and anyone with 'all-projects' bypass scoping. */
    public function seesAllProjects(): bool
    {
        return (bool) $this->is_super_admin
            || $this->type === 'admin'
            || $this->can('all-projects');
    }

    /**
     * Project ids this user may see. null means "all" (no restriction) — callers
     * use `when($ids !== null, ...)` so admins skip the whereIn entirely.
     *
     * @return array<int>|null
     */
    public function visibleProjectIds(): ?array
    {
        return $this->seesAllProjects() ? null : $this->assignedProjectIds();
    }

    /** Branches this user may operate in (with an optional per-branch role). */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('branch_role')->withTimestamps();
    }

    /** Admins and super admins can view/switch across every branch. */
    public function seesAllBranches(): bool
    {
        return (bool) $this->is_super_admin
            || $this->type === 'admin'
            || $this->can('all-branches');
    }

    /** @return array<int> */
    public function accessibleBranchIds(): array
    {
        return $this->branches()->pluck('branches.id')->all();
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch');
    }
}
