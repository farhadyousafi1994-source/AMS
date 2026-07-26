<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ActivityLog::with('user')
                ->where('company_id', Tenant::id())
                ->latest()->limit(50)->get()
        );
    }
}
