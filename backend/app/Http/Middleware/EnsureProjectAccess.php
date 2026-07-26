<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a user from any route bound to a {project} they are not assigned to.
 * Applied to project sub-resource routes so per-project scoping is enforced in
 * one place. Users with 'all-projects' (admin/President/Accountant) pass through.
 */
class EnsureProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');
        if ($project !== null) {
            $id = is_object($project) ? $project->id : (int) $project;
            $ids = $request->user()?->visibleProjectIds();
            abort_if($ids !== null && ! in_array($id, $ids, true), 403, 'This project is not assigned to you.');
        }

        return $next($request);
    }
}
