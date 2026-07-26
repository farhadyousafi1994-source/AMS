<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

class TrashController extends Controller
{
    public function counts(): JsonResponse
    {
        $companyId = Tenant::id();

        return response()->json([
            'companies' => Company::onlyTrashed()->count(),
            'branches'  => Branch::withoutGlobalScopes()->onlyTrashed()->where('company_id', $companyId)->count(),
            'users'     => User::onlyTrashed()->where('company_id', $companyId)->count(),
        ]);
    }
}
