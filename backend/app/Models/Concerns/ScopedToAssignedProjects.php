<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a project-bound model to the projects a user is assigned to.
 * Admins and Super Admins see everything; a field user (supervisor/engineer)
 * sees only their assigned projects. Enforced on every supervisor-module query,
 * never relying on the UI to hide rows. Assumes the model has a project_id.
 */
trait ScopedToAssignedProjects
{
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->seesAllProjects()) {
            return $query;
        }

        return $query->whereIn('project_id', $user->visibleProjectIds() ?? []);
    }
}
