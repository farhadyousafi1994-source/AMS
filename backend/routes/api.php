<?php

use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\Branch\BranchController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\User\UserController;
use App\Models\ActivityLog;
use App\Support\Tenant;
use Illuminate\Support\Facades\Route;

// Public auth
Route::post('login', [AuthController::class, 'login']);
Route::get('auth', [AuthController::class, 'check']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->middleware(['super_admin'])->group(function () {
        Route::get('companies', [SuperAdminController::class, 'companies']);
        Route::get('companies/{company}/stats', [SuperAdminController::class, 'companyStats']);
        Route::get('users', [SuperAdminController::class, 'users']);
        Route::put('users/{user}/toggle-super-admin', [SuperAdminController::class, 'toggleSuperAdmin']);
        Route::delete('companies/{company}', [SuperAdminController::class, 'destroyCompany']);
    });

    // VIP Control Center — Platform Owner only (server-enforced, role-independent)
    Route::prefix('platform')->middleware(['platform_owner'])->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Platform\PlatformController::class, 'dashboard']);
        Route::get('organizations', [\App\Http\Controllers\Platform\PlatformController::class, 'organizations']);
        Route::post('organizations', [\App\Http\Controllers\Platform\PlatformController::class, 'createOrganization']);
        Route::put('organizations/{company}/toggle', [\App\Http\Controllers\Platform\PlatformController::class, 'toggleOrganization']);
        Route::put('organizations/{company}/self-service', [\App\Http\Controllers\Platform\PlatformController::class, 'setSelfService']);
        Route::get('branches', [\App\Http\Controllers\Platform\PlatformController::class, 'branches']);
        Route::post('branches', [\App\Http\Controllers\Platform\PlatformController::class, 'createBranch']);
        Route::put('branches/{branch}/rename', [\App\Http\Controllers\Platform\PlatformController::class, 'renameBranch']);
        Route::put('branches/{branch}/toggle', [\App\Http\Controllers\Platform\PlatformController::class, 'toggleBranch']);
        Route::put('branches/{branch}/transfer', [\App\Http\Controllers\Platform\PlatformController::class, 'transferBranch']);
        Route::delete('branches/{branch}/archive', [\App\Http\Controllers\Platform\PlatformController::class, 'archiveBranch']);
        Route::delete('branches/{branch}', [\App\Http\Controllers\Platform\PlatformController::class, 'deleteBranch']);
        Route::get('requests', [\App\Http\Controllers\Platform\PlatformController::class, 'requests']);
        Route::put('requests/{platformRequest}/decide', [\App\Http\Controllers\Platform\PlatformController::class, 'decideRequest']);
        Route::get('audit', [\App\Http\Controllers\Platform\PlatformController::class, 'audit']);
    });

    // A tenant admin raises a platform request (owner decides it later)
    Route::post('platform/requests', [\App\Http\Controllers\Platform\PlatformController::class, 'submitRequest']);

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // Company management (master scope, no tenant required)
    Route::get('company', [CompanyController::class, 'index']);
    Route::post('company/store', [CompanyController::class, 'store']);
    Route::get('company/select/{company}', [CompanyController::class, 'select']);
    Route::get('company/{company}', [CompanyController::class, 'show']);
    Route::put('company/{company}', [CompanyController::class, 'update']);
    Route::delete('company/{company}', [CompanyController::class, 'destroy']);

    // Tenant-scoped routes
    Route::middleware(['tenant', 'branch', 'project.access'])->group(function () {
        Route::get('dashboard_data', [DashboardController::class, 'dashboard']);
        Route::get('search', [\App\Http\Controllers\SearchController::class, 'index']);

        Route::apiResource('users', UserController::class);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);

        Route::apiResource('branches', BranchController::class);
        Route::post('me/branch', [BranchController::class, 'switch']);
        Route::put('me/profile', [AuthController::class, 'updateProfile']);

        // Universal attachments (photos / receipts / documents on any record)
        Route::get('attachments/archive', [\App\Http\Controllers\AttachmentController::class, 'archive']);
        Route::get('attachments', [\App\Http\Controllers\AttachmentController::class, 'index']);
        Route::post('attachments', [\App\Http\Controllers\AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/view', [\App\Http\Controllers\AttachmentController::class, 'view']);
        Route::delete('attachments/{attachment}', [\App\Http\Controllers\AttachmentController::class, 'destroy']);

        // Projects & Sites (Part A)
        Route::get('projects-next-code', [\App\Http\Controllers\Project\ProjectController::class, 'nextCodePreview']);
        Route::get('projects/{project}/activity', [\App\Http\Controllers\Project\ProjectController::class, 'activity']);
        Route::apiResource('projects', \App\Http\Controllers\Project\ProjectController::class);
        Route::post('projects/{project}/sites', [\App\Http\Controllers\Project\ProjectSiteController::class, 'store']);
        Route::put('sites/{site}', [\App\Http\Controllers\Project\ProjectSiteController::class, 'update']);
        Route::delete('sites/{site}', [\App\Http\Controllers\Project\ProjectSiteController::class, 'destroy']);
        Route::get('projects/{project}/tasks', [\App\Http\Controllers\Project\ProjectTaskController::class, 'index']);
        Route::post('projects/{project}/tasks', [\App\Http\Controllers\Project\ProjectTaskController::class, 'store']);
        Route::put('tasks/{task}', [\App\Http\Controllers\Project\ProjectTaskController::class, 'update']);
        Route::delete('tasks/{task}', [\App\Http\Controllers\Project\ProjectTaskController::class, 'destroy']);
        // Lifts — concrete pours / earthwork layers with inspection hold points
        Route::get('tasks/{task}/lifts', [\App\Http\Controllers\Project\TaskLiftController::class, 'index']);
        Route::post('tasks/{task}/lifts', [\App\Http\Controllers\Project\TaskLiftController::class, 'store']);
        Route::get('projects/{project}/lifts', [\App\Http\Controllers\Project\TaskLiftController::class, 'projectIndex']);
        Route::put('lifts/{lift}', [\App\Http\Controllers\Project\TaskLiftController::class, 'update']);
        Route::delete('lifts/{lift}', [\App\Http\Controllers\Project\TaskLiftController::class, 'destroy']);
        Route::post('projects/{project}/milestones', [\App\Http\Controllers\Project\ProjectMilestoneController::class, 'store']);
        Route::put('milestones/{milestone}', [\App\Http\Controllers\Project\ProjectMilestoneController::class, 'update']);
        Route::delete('milestones/{milestone}', [\App\Http\Controllers\Project\ProjectMilestoneController::class, 'destroy']);
        Route::get('projects/{project}/site-logs', [\App\Http\Controllers\Project\DailySiteLogController::class, 'index']);
        Route::post('projects/{project}/site-logs', [\App\Http\Controllers\Project\DailySiteLogController::class, 'store']);
        Route::put('site-logs/{siteLog}', [\App\Http\Controllers\Project\DailySiteLogController::class, 'update']);
        Route::delete('site-logs/{siteLog}', [\App\Http\Controllers\Project\DailySiteLogController::class, 'destroy']);
        // Change Orders (Variation Orders) — revise contract value on approval
        Route::get('change-orders', [\App\Http\Controllers\Project\ChangeOrderController::class, 'index']);
        Route::post('change-orders', [\App\Http\Controllers\Project\ChangeOrderController::class, 'store']);
        Route::get('projects/{project}/change-orders', [\App\Http\Controllers\Project\ChangeOrderController::class, 'projectIndex']);
        Route::put('change-orders/{changeOrder}', [\App\Http\Controllers\Project\ChangeOrderController::class, 'update']);
        Route::put('change-orders/{changeOrder}/submit', [\App\Http\Controllers\Project\ChangeOrderController::class, 'submit']);
        Route::put('change-orders/{changeOrder}/decide', [\App\Http\Controllers\Project\ChangeOrderController::class, 'decide']);
        Route::delete('change-orders/{changeOrder}', [\App\Http\Controllers\Project\ChangeOrderController::class, 'destroy']);
        Route::get('change-orders/{changeOrder}/attachment', [\App\Http\Controllers\Project\ChangeOrderController::class, 'attachment']);

        // HSE / Safety Incident register
        Route::get('safety-incidents', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'index']);
        Route::post('safety-incidents', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'store']);
        Route::put('safety-incidents/{safetyIncident}', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'update']);
        Route::put('safety-incidents/{safetyIncident}/close', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'close']);
        Route::put('safety-incidents/{safetyIncident}/reopen', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'reopen']);
        Route::delete('safety-incidents/{safetyIncident}', [\App\Http\Controllers\Safety\SafetyIncidentController::class, 'destroy']);

        Route::get('projects/{project}/subcontractors', [\App\Http\Controllers\Project\SubcontractorController::class, 'index']);
        Route::post('projects/{project}/subcontractors', [\App\Http\Controllers\Project\SubcontractorController::class, 'store']);
        Route::get('subcontractors/{subcontractor}', [\App\Http\Controllers\Project\SubcontractorController::class, 'show']);
        Route::put('subcontractors/{subcontractor}', [\App\Http\Controllers\Project\SubcontractorController::class, 'update']);
        Route::delete('subcontractors/{subcontractor}', [\App\Http\Controllers\Project\SubcontractorController::class, 'destroy']);
        Route::post('subcontractors/{subcontractor}/payments', [\App\Http\Controllers\Project\SubcontractorPaymentController::class, 'store']);
        Route::put('subcontractor-payments/{payment}', [\App\Http\Controllers\Project\SubcontractorPaymentController::class, 'update']);
        Route::delete('subcontractor-payments/{payment}', [\App\Http\Controllers\Project\SubcontractorPaymentController::class, 'destroy']);
        Route::get('projects/{project}/documents', [\App\Http\Controllers\Project\ProjectDocumentController::class, 'index']);
        Route::post('projects/{project}/documents', [\App\Http\Controllers\Project\ProjectDocumentController::class, 'store']);
        Route::get('documents/{document}/download', [\App\Http\Controllers\Project\ProjectDocumentController::class, 'download']);
        Route::put('documents/{document}', [\App\Http\Controllers\Project\ProjectDocumentController::class, 'update']);
        Route::delete('documents/{document}', [\App\Http\Controllers\Project\ProjectDocumentController::class, 'destroy']);

        // Finance & Accounting (Part B) — currency + daily-rate + rate-lock foundation
        Route::apiResource('currencies', \App\Http\Controllers\Finance\CurrencyController::class)->except(['show']);
        Route::put('currencies/{currency}/set-base', [\App\Http\Controllers\Finance\CurrencyController::class, 'setBase']);
        Route::get('exchange-rates', [\App\Http\Controllers\Finance\ExchangeRateController::class, 'index']);
        Route::get('exchange-rates/current', [\App\Http\Controllers\Finance\ExchangeRateController::class, 'current']);
        Route::post('exchange-rates', [\App\Http\Controllers\Finance\ExchangeRateController::class, 'store']);
        Route::apiResource('expenses', \App\Http\Controllers\Finance\ExpenseController::class)->except(['show']);
        Route::put('expenses/{expense}/approve', [\App\Http\Controllers\Finance\ExpenseController::class, 'approve']);
        Route::get('expenses/{expense}/attachment', [\App\Http\Controllers\Finance\ExpenseController::class, 'attachment']);
        // Office & Home expense dashboards + partners + budgets
        Route::get('office-expenses/dashboard', [\App\Http\Controllers\Finance\ExpenseInsightsController::class, 'office']);
        Route::get('home-expenses/dashboard', [\App\Http\Controllers\Finance\ExpenseInsightsController::class, 'home']);
        Route::apiResource('partners', \App\Http\Controllers\Finance\PartnerController::class)->only(['index', 'store', 'update', 'destroy']);

        // Shareholder (partner) equity — deposits / withdrawals against the General Budget
        Route::get('shareholders', [\App\Http\Controllers\Finance\ShareholderController::class, 'index']);
        Route::get('shareholders/activity', [\App\Http\Controllers\Finance\ShareholderController::class, 'activity']);
        Route::get('shareholders/{partner}', [\App\Http\Controllers\Finance\ShareholderController::class, 'show']);
        Route::post('shareholders/{partner}/deposit', [\App\Http\Controllers\Finance\ShareholderController::class, 'deposit']);
        Route::post('shareholders/{partner}/withdraw', [\App\Http\Controllers\Finance\ShareholderController::class, 'withdraw']);
        Route::get('expense-budgets', [\App\Http\Controllers\Finance\ExpenseBudgetController::class, 'index']);
        Route::post('expense-budgets', [\App\Http\Controllers\Finance\ExpenseBudgetController::class, 'store']);
        Route::delete('expense-budgets/{budget}', [\App\Http\Controllers\Finance\ExpenseBudgetController::class, 'destroy']);
        Route::apiResource('invoices', \App\Http\Controllers\Finance\InvoiceController::class);
        Route::apiResource('receipts', \App\Http\Controllers\Finance\ReceiptController::class)->only(['index', 'store', 'destroy']);

        // Party accounts (حسابات) — credit/debit ledger outside the cap table
        Route::apiResource('parties', \App\Http\Controllers\Finance\PartyController::class);
        Route::post('parties/{party}/transactions', [\App\Http\Controllers\Finance\PartyController::class, 'addTransaction']);
        Route::put('party-transactions/{transaction}/confirm', [\App\Http\Controllers\Finance\PartyController::class, 'confirmTransaction']);
        Route::delete('party-transactions/{transaction}', [\App\Http\Controllers\Finance\PartyController::class, 'deleteTransaction']);
        Route::get('party-transactions/{transaction}/attachment', [\App\Http\Controllers\Finance\PartyController::class, 'downloadAttachment']);
        Route::get('projects/{project}/party-transactions', [\App\Http\Controllers\Finance\PartyController::class, 'projectTransactions']);

        // General Budget (company treasury) — available/reserved ledger
        Route::get('treasury/summary', [\App\Http\Controllers\Finance\TreasuryController::class, 'summary']);
        Route::get('treasury', [\App\Http\Controllers\Finance\TreasuryController::class, 'index']);
        Route::post('treasury', [\App\Http\Controllers\Finance\TreasuryController::class, 'store']);
        Route::delete('treasury/{treasury}', [\App\Http\Controllers\Finance\TreasuryController::class, 'destroy']);

        // Supervisor & site management (Slice 1) — field cash purchases,
        // approval flow, cash advances, receipt upload, invoice archive.
        Route::get('purchase-categories', [\App\Http\Controllers\Supervisor\PurchaseCategoryController::class, 'index']);
        Route::post('purchase-categories', [\App\Http\Controllers\Supervisor\PurchaseCategoryController::class, 'store']);
        Route::delete('purchase-categories/{purchaseCategory}', [\App\Http\Controllers\Supervisor\PurchaseCategoryController::class, 'destroy']);
        Route::apiResource('purchase-requests', \App\Http\Controllers\Supervisor\PurchaseRequestController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::put('purchase-requests/{purchaseRequest}/decide', [\App\Http\Controllers\Supervisor\PurchaseRequestController::class, 'decide']);
        Route::post('purchase-requests/{purchaseRequest}/advance', [\App\Http\Controllers\Supervisor\PurchaseRequestController::class, 'releaseAdvance']);
        Route::post('purchase-requests/{purchaseRequest}/receipt', [\App\Http\Controllers\Supervisor\PurchaseRequestController::class, 'uploadReceipt']);
        Route::put('purchase-requests/{purchaseRequest}/close', [\App\Http\Controllers\Supervisor\PurchaseRequestController::class, 'close']);
        Route::get('site-invoices', [\App\Http\Controllers\Supervisor\SiteInvoiceController::class, 'index']);
        Route::get('site-invoices/{siteInvoice}/image', [\App\Http\Controllers\Supervisor\SiteInvoiceController::class, 'image']);
        Route::delete('site-invoices/{siteInvoice}', [\App\Http\Controllers\Supervisor\SiteInvoiceController::class, 'destroy']);
        Route::get('projects/{project}/team', [\App\Http\Controllers\Supervisor\ProjectTeamController::class, 'index']);
        Route::put('projects/{project}/team', [\App\Http\Controllers\Supervisor\ProjectTeamController::class, 'sync']);

        // Slice 2 — worker registry + daily field attendance (photo + GPS + offline)
        Route::apiResource('workers', \App\Http\Controllers\Supervisor\WorkerController::class)->except(['show']);
        Route::get('workers/{worker}/photo', [\App\Http\Controllers\Supervisor\WorkerController::class, 'photo']);
        Route::get('worker-attendances', [\App\Http\Controllers\Supervisor\WorkerAttendanceController::class, 'index']);
        Route::post('worker-attendances', [\App\Http\Controllers\Supervisor\WorkerAttendanceController::class, 'store']);
        Route::post('worker-attendances/sync', [\App\Http\Controllers\Supervisor\WorkerAttendanceController::class, 'sync']);
        Route::get('worker-attendances/{workerAttendance}/photo', [\App\Http\Controllers\Supervisor\WorkerAttendanceController::class, 'photo']);
        Route::delete('worker-attendances/{workerAttendance}', [\App\Http\Controllers\Supervisor\WorkerAttendanceController::class, 'destroy']);

        // Cross-project subcontractors (استادکاران) — registry + fingerprint payout
        Route::get('tradesmen/fingerprint/{fingerprint}', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'fingerprint']);
        Route::apiResource('tradesmen', \App\Http\Controllers\Subcontractor\TradesmanController::class);
        Route::get('tradesmen/{tradesman}/photo', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'photo']);
        Route::post('tradesmen/{tradesman}/engagements', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'addEngagement']);
        Route::post('tradesmen/{tradesman}/payments', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'addPayment']);
        Route::post('tradesmen/{tradesman}/measurements', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'addMeasurement']);
        Route::delete('work-measurements/{measurement}', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'deleteMeasurement']);
        Route::post('tradesmen/{tradesman}/ratings', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'addRating']);
        Route::put('subcontractor-payments/{payment}/confirm-fingerprint', [\App\Http\Controllers\Subcontractor\TradesmanController::class, 'confirmPaymentFingerprint']);

        // HR settings — Departments & Designations (lightweight master data)
        Route::apiResource('departments', \App\Http\Controllers\HR\DepartmentController::class)->except(['show']);
        Route::apiResource('designations', \App\Http\Controllers\HR\DesignationController::class)->except(['show']);
        Route::apiResource('employees', \App\Http\Controllers\HR\EmployeeController::class);
        // Rich employee profile — studies, documents, specializations, salary history
        Route::get('employees/{employee}/profile', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'profile']);
        Route::post('employees/{employee}/educations', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'addEducation']);
        Route::delete('employee-educations/{education}', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'deleteEducation']);
        Route::put('employees/{employee}/specializations', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'updateSpecializations']);
        Route::post('employees/{employee}/documents', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'addDocument']);
        Route::delete('employee-documents/{document}', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'deleteDocument']);
        Route::get('employee-documents/{document}/download', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'downloadDocument']);
        Route::post('employees/{employee}/photo', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'uploadPhoto']);
        Route::get('employees/{employee}/photo', [\App\Http\Controllers\HR\EmployeeProfileController::class, 'photo']);

        // Project resources — returnable assets + consumable materials
        Route::get('projects/{project}/resources', [\App\Http\Controllers\Project\ProjectResourceController::class, 'index']);
        Route::post('projects/{project}/assets', [\App\Http\Controllers\Project\ProjectResourceController::class, 'attachAsset']);
        Route::delete('project-assets/{projectAsset}', [\App\Http\Controllers\Project\ProjectResourceController::class, 'detachAsset']);
        Route::post('projects/{project}/materials', [\App\Http\Controllers\Project\ProjectResourceController::class, 'addMaterial']);
        Route::put('project-materials/{material}', [\App\Http\Controllers\Project\ProjectResourceController::class, 'updateMaterial']);
        Route::delete('project-materials/{material}', [\App\Http\Controllers\Project\ProjectResourceController::class, 'deleteMaterial']);

        // Investors (cross-project) + project cap table (investments)
        Route::apiResource('investors', \App\Http\Controllers\Investor\InvestorController::class);
        Route::get('projects/{project}/investments', [\App\Http\Controllers\Project\ProjectInvestmentController::class, 'index']);
        Route::post('projects/{project}/investments', [\App\Http\Controllers\Project\ProjectInvestmentController::class, 'store']);
        Route::put('investments/{investment}', [\App\Http\Controllers\Project\ProjectInvestmentController::class, 'update']);
        Route::delete('investments/{investment}', [\App\Http\Controllers\Project\ProjectInvestmentController::class, 'destroy']);

        // Subcontractor Contracts (standalone, cross-project) — client / subcontractor
        Route::apiResource('contracts', \App\Http\Controllers\Contract\ContractController::class);
        Route::post('contracts/{contract}/milestones', [\App\Http\Controllers\Contract\ContractController::class, 'addMilestone']);
        Route::put('contract-milestones/{milestone}', [\App\Http\Controllers\Contract\ContractController::class, 'updateMilestone']);
        Route::delete('contract-milestones/{milestone}', [\App\Http\Controllers\Contract\ContractController::class, 'deleteMilestone']);
        Route::post('contracts/{contract}/payments', [\App\Http\Controllers\Contract\ContractController::class, 'addPayment']);
        Route::delete('contract-payments/{payment}', [\App\Http\Controllers\Contract\ContractController::class, 'deletePayment']);

        // Procurement (Part C) — suppliers, POs, and the consumable warehouse
        Route::apiResource('suppliers', \App\Http\Controllers\Procurement\SupplierController::class);
        Route::apiResource('stock-items', \App\Http\Controllers\Procurement\StockController::class)->parameters(['stock-items' => 'stockItem']);
        Route::post('stock-items/{stockItem}/movements', [\App\Http\Controllers\Procurement\StockController::class, 'addMovement']);
        Route::delete('stock-movements/{movement}', [\App\Http\Controllers\Procurement\StockController::class, 'deleteMovement']);
        Route::apiResource('purchase-orders', \App\Http\Controllers\Procurement\PurchaseOrderController::class)->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::put('purchase-orders/{purchaseOrder}/receive', [\App\Http\Controllers\Procurement\PurchaseOrderController::class, 'receive']);

        // Assets (Part C) — returnable resources with per-unit + by-count tracking
        Route::apiResource('assets', \App\Http\Controllers\Asset\AssetController::class);
        Route::post('assets/{asset}/maintenance', [\App\Http\Controllers\Asset\AssetController::class, 'addMaintenance']);

        // Cross-branch asset transfers (request / approve / reject)
        Route::get('asset-transfers', [\App\Http\Controllers\Asset\AssetTransferController::class, 'index']);
        Route::post('asset-transfers', [\App\Http\Controllers\Asset\AssetTransferController::class, 'store']);
        Route::put('asset-transfers/{transfer}/decide', [\App\Http\Controllers\Asset\AssetTransferController::class, 'decide']);
        Route::delete('asset-maintenance/{maintenanceLog}', [\App\Http\Controllers\Asset\AssetController::class, 'deleteMaintenance']);

        // Reports engine — shared filters, exported as PDF / Excel / Word
        Route::get('reports/{type}', [\App\Http\Controllers\Report\ReportController::class, 'generate']);

        // Options Registry (dynamic bilingual dropdowns)
        Route::get('lookups', [\App\Http\Controllers\LookupController::class, 'index']);
        Route::get('lookups/groups', [\App\Http\Controllers\LookupController::class, 'groups']);
        Route::post('lookups', [\App\Http\Controllers\LookupController::class, 'store']);
        Route::put('lookups/{lookup}', [\App\Http\Controllers\LookupController::class, 'update']);
        Route::delete('lookups/{lookup}', [\App\Http\Controllers\LookupController::class, 'destroy']);

        // Control Room — interface visibility & ordering
        Route::get('ui-settings', [\App\Http\Controllers\UiSettingController::class, 'index']);
        Route::post('ui-settings/bulk', [\App\Http\Controllers\UiSettingController::class, 'bulk']);
        Route::delete('ui-settings', [\App\Http\Controllers\UiSettingController::class, 'reset']);

        // Centralized Payment Center — cross-module requests + approval workflow
        Route::get('payment-center', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'index']);
        Route::post('payment-center', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'store']);
        Route::get('payment-center/rules', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'rules']);
        Route::post('payment-center/rules', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'storeRule']);
        Route::put('payment-center/rules/{rule}', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'updateRule']);
        Route::delete('payment-center/rules/{rule}', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'destroyRule']);
        Route::get('payment-center/{paymentRequest}', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'show']);
        Route::put('payment-center/{paymentRequest}/approve', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'approve']);
        Route::put('payment-center/{paymentRequest}/reject', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'reject']);
        Route::put('payment-center/{paymentRequest}/process', [\App\Http\Controllers\Finance\PaymentCenterController::class, 'process']);

        // Fingerprint / biometric subsystem
        Route::get('fingerprint/settings', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'settings']);
        Route::put('fingerprint/settings', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'updateSettings']);
        Route::get('fingerprint/devices/detect', [\App\Http\Controllers\Fingerprint\FingerprintDeviceController::class, 'detect']);
        Route::post('fingerprint/devices/{fingerprintDevice}/test', [\App\Http\Controllers\Fingerprint\FingerprintDeviceController::class, 'test']);
        Route::apiResource('fingerprint/devices', \App\Http\Controllers\Fingerprint\FingerprintDeviceController::class)
            ->parameters(['devices' => 'fingerprintDevice'])->except(['show']);
        Route::post('fingerprint/capture', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'capture']);
        Route::get('fingerprint/enrollments', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'enrollments']);
        Route::post('fingerprint/enroll', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'enroll']);
        Route::delete('fingerprint/enrollments/{enrollment}', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'removeEnrollment']);
        Route::post('fingerprint/verify', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'verify']);
        Route::post('fingerprint/payments/{payment}/verify', [\App\Http\Controllers\Fingerprint\FingerprintController::class, 'verifyPayment']);

        // System
        Route::get('activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);
        Route::get('trash-counts', [TrashController::class, 'counts']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-read', [NotificationController::class, 'markRead']);
        Route::get('backup/download', [BackupController::class, 'download']);
        Route::get('backup/list', [BackupController::class, 'index']);
        Route::post('backup/restore', [BackupController::class, 'restore']);

        // Offline sync engine (device IndexedDB <-> central PostgreSQL)
        Route::post('sync/pull', [\App\Http\Controllers\Sync\SyncController::class, 'pull']);
        Route::post('sync/push', [\App\Http\Controllers\Sync\SyncController::class, 'push']);

        // HR — attendance sheet + payroll runs (Part D)
        Route::get('attendance', [\App\Http\Controllers\HR\AttendanceController::class, 'day']);
        Route::post('attendance', [\App\Http\Controllers\HR\AttendanceController::class, 'save']);
        Route::post('attendance/record', [\App\Http\Controllers\HR\AttendanceController::class, 'record']);
        Route::post('attendance/sync-device', [\App\Http\Controllers\HR\AttendanceController::class, 'syncDevice']);
        Route::get('payroll-runs', [\App\Http\Controllers\HR\PayrollController::class, 'index']);
        Route::post('payroll-runs', [\App\Http\Controllers\HR\PayrollController::class, 'generate']);
        Route::get('payroll-runs/{payroll}', [\App\Http\Controllers\HR\PayrollController::class, 'show']);
        Route::put('payroll-items/{item}', [\App\Http\Controllers\HR\PayrollController::class, 'updateItem']);
        Route::put('payroll-runs/{payroll}/pay', [\App\Http\Controllers\HR\PayrollController::class, 'markPaid']);
        Route::delete('payroll-runs/{payroll}', [\App\Http\Controllers\HR\PayrollController::class, 'destroy']);

        // HR — leave management
        Route::get('leaves', [\App\Http\Controllers\HR\LeaveController::class, 'index']);
        Route::post('leaves', [\App\Http\Controllers\HR\LeaveController::class, 'store']);
        Route::put('leaves/{leave}/decide', [\App\Http\Controllers\HR\LeaveController::class, 'decide']);
        Route::delete('leaves/{leave}', [\App\Http\Controllers\HR\LeaveController::class, 'destroy']);
    });
});
