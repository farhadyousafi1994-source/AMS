<?php

return [

    'key_column' => 'uuid',

    'revision_column' => 'revision',

    'pull_limit' => 500,

    'tables' => [
        'projects' => App\Models\Project::class,
        'project_tasks' => App\Models\ProjectTask::class,
        'project_milestones' => App\Models\ProjectMilestone::class,
        'daily_site_logs' => App\Models\DailySiteLog::class,
        'attendance_records' => App\Models\AttendanceRecord::class,
        'worker_attendances' => App\Models\WorkerAttendance::class,
        'workers' => App\Models\Worker::class,
        'purchase_requests' => App\Models\PurchaseRequest::class,
        'site_invoices' => App\Models\SiteInvoice::class,
        'cash_advances' => App\Models\CashAdvance::class,
        'expenses' => App\Models\Expense::class,
        'invoices' => App\Models\Invoice::class,
        'receipts' => App\Models\Receipt::class,
        'safety_incidents' => App\Models\SafetyIncident::class,
        'employees' => App\Models\Employee::class,
        'leaves' => App\Models\Leave::class,
        'parties' => App\Models\Party::class,
        'party_transactions' => App\Models\PartyTransaction::class,
        'suppliers' => App\Models\Supplier::class,
        'purchase_orders' => App\Models\PurchaseOrder::class,
        'stock_items' => App\Models\StockItem::class,
        'stock_movements' => App\Models\StockMovement::class,
        'contracts' => App\Models\Contract::class,
        'assets' => App\Models\Asset::class,
    ],
];
