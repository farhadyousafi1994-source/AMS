<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Investor;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestorController extends Controller
{
    public function index(): JsonResponse
    {
        $investors = Investor::withCount('investments')
            ->withSum('investments as total_capital', 'capital')
            ->withSum('investments as total_profit', 'profit_received')
            ->orderBy('name')
            ->get();

        return response()->json($investors);
    }

    public function show(Investor $investor): JsonResponse
    {
        $investor->load(['investments' => fn ($q) => $q->with('project:id,name,status')->orderByDesc('id')]);
        $investor->setAttribute('total_capital', round((float) $investor->investments->sum('capital'), 2));
        $investor->setAttribute('total_profit', round((float) $investor->investments->sum('profit_received'), 2));

        return response()->json($investor);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $this->nextCode();

        $investor = Investor::create($data);

        ActivityLog::log('created', 'Investor', "Added investor \"{$investor->name}\" ({$investor->code})");

        return response()->json($investor, 201);
    }

    public function update(Request $request, Investor $investor): JsonResponse
    {
        $investor->update($this->rules($request));

        ActivityLog::log('updated', 'Investor', "Updated investor \"{$investor->name}\"");

        return response()->json($investor);
    }

    public function destroy(Investor $investor): JsonResponse
    {
        $name = $investor->name;
        $investor->delete();

        ActivityLog::log('deleted', 'Investor', "Deleted investor \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function nextCode(): string
    {
        $seq = Investor::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'INV-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:individual,company,government'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'register_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
