<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\Tenant;
use Illuminate\Http\Request;

class NotificationController extends Controller {
    public function index() {
        return response()->json(
            Notification::where('company_id', Tenant::id())
                ->latest()->limit(30)->get()
        );
    }
    public function markRead(Request $request) {
        $q = Notification::where('company_id', Tenant::id())->whereNull('read_at');
        // Optional `id` marks just that one notification; otherwise mark all.
        if ($request->filled('id')) {
            $q->where('id', $request->input('id'));
        }
        $q->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
