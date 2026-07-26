const routes = [
  {
    path: '/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: '', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') }
    ],
    meta: { guest: true }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },

      // Users & Roles
      { path: 'users', name: 'users', component: () => import('@/pages/users/UsersPage.vue'), meta: { permission: 'user-list' } },
      { path: 'users/create', name: 'user-create', component: () => import('@/pages/users/UserForm.vue'), meta: { permission: 'user-create' } },
      { path: 'users/edit/:id', name: 'user-edit', component: () => import('@/pages/users/UserForm.vue'), meta: { permission: 'user-edit' } },
      { path: 'roles', name: 'roles', component: () => import('@/pages/roles/RolesPage.vue'), meta: { permission: 'role-list' } },
      { path: 'roles/create', name: 'role-create', component: () => import('@/pages/roles/RoleForm.vue'), meta: { permission: 'role-create' } },
      { path: 'roles/edit/:id', name: 'role-edit', component: () => import('@/pages/roles/RoleForm.vue'), meta: { permission: 'role-edit' } },

      // Branches (multi-branch)
      { path: 'branches', name: 'branches', component: () => import('@/pages/branches/BranchesPage.vue'), meta: { permission: 'branch-list' } },

      // Options Registry (dynamic bilingual dropdowns)
      { path: 'options', name: 'options', component: () => import('@/pages/system/OptionsRegistryPage.vue'), meta: { permission: 'lookup-list' } },

      // Control Room (interface visibility & ordering)
      { path: 'control-room', name: 'control-room', component: () => import('@/pages/system/ControlRoomPage.vue'), meta: { permission: 'ui-setting-list' } },

      // Fingerprint / biometric settings
      { path: 'fingerprint', name: 'fingerprint', component: () => import('@/pages/system/FingerprintSettingsPage.vue'), meta: { permission: 'fingerprint-list' } },

      // Projects & Sites (Part A)
      { path: 'projects', name: 'projects', component: () => import('@/pages/projects/ProjectsPage.vue'), meta: { permission: 'project-list' } },
      { path: 'projects/create', name: 'project-create', component: () => import('@/pages/projects/ProjectForm.vue'), meta: { permission: 'project-create' } },
      { path: 'projects/edit/:id', name: 'project-edit', component: () => import('@/pages/projects/ProjectForm.vue'), meta: { permission: 'project-edit' } },
      { path: 'projects/:id', name: 'project-show', component: () => import('@/pages/projects/ProjectShow.vue'), meta: { permission: 'project-list' } },

      // Finance & Accounting (Part B)
      { path: 'finance/invoices', name: 'invoices', component: () => import('@/pages/finance/InvoicesPage.vue'), meta: { permission: 'invoice-list' } },
      { path: 'finance/shareholders', name: 'shareholders', component: () => import('@/pages/finance/ShareholdersPage.vue'), meta: { permission: 'partner-list' } },
      { path: 'finance/receipts', name: 'receipts', component: () => import('@/pages/finance/ReceiptsPage.vue'), meta: { permission: 'receipt-list' } },
      { path: 'finance/expenses', name: 'expenses', component: () => import('@/pages/finance/ExpensesPage.vue'), meta: { permission: 'expense-list' } },
      { path: 'finance/office-expenses', name: 'office-expenses', component: () => import('@/pages/finance/OfficeExpensesPage.vue'), meta: { permission: 'office-expense-list' } },
      { path: 'finance/home-expenses', name: 'home-expenses', component: () => import('@/pages/finance/HomeExpensesPage.vue'), meta: { permission: 'home-expense-list' } },
      { path: 'finance/exchange-rates', name: 'exchange-rates', component: () => import('@/pages/finance/ExchangeRatesPage.vue'), meta: { permission: 'exchange-rate-list' } },
      { path: 'finance/currencies', name: 'currencies', component: () => import('@/pages/finance/CurrenciesPage.vue'), meta: { permission: 'currency-list' } },
      { path: 'finance/treasury', name: 'treasury', component: () => import('@/pages/finance/TreasuryPage.vue'), meta: { permission: 'treasury-list' } },
      { path: 'finance/payment-center', name: 'payment-center', component: () => import('@/pages/finance/PaymentCenterPage.vue'), meta: { permission: 'payment-request-list' } },

      // Assets (Part C)
      { path: 'assets', name: 'assets', component: () => import('@/pages/assets/AssetsPage.vue'), meta: { permission: 'asset-list' } },

      // Procurement (Part C)
      { path: 'procurement/suppliers', name: 'suppliers', component: () => import('@/pages/procurement/SuppliersPage.vue'), meta: { permission: 'supplier-list' } },
      { path: 'procurement/stock', name: 'stock', component: () => import('@/pages/procurement/StockPage.vue'), meta: { permission: 'stock-item-list' } },
      { path: 'procurement/purchase-orders', name: 'purchase-orders', component: () => import('@/pages/procurement/PurchaseOrdersPage.vue'), meta: { permission: 'purchase-order-list' } },

      // Investors (cross-project)
      { path: 'investors', name: 'investors', component: () => import('@/pages/investors/InvestorsPage.vue'), meta: { permission: 'investor-list' } },

      // Subcontractor Contracts (standalone, cross-project)
      { path: 'contracts', name: 'contracts', component: () => import('@/pages/contracts/ContractsPage.vue'), meta: { permission: 'contract-list' } },
      { path: 'change-orders', name: 'change-orders', component: () => import('@/pages/projects/ChangeOrdersPage.vue'), meta: { permission: 'change-order-list' } },
      { path: 'safety/incidents', name: 'safety-incidents', component: () => import('@/pages/safety/IncidentsPage.vue'), meta: { permission: 'incident-list' } },

      // Cross-project subcontractors (استادکاران)
      { path: 'subcontractors', name: 'subcontractors', component: () => import('@/pages/subcontractors/SubcontractorsPage.vue'), meta: { permission: 'tradesman-list' } },
      { path: 'subcontractors/:id', name: 'subcontractor-show', component: () => import('@/pages/subcontractors/SubcontractorShow.vue'), meta: { permission: 'tradesman-show' } },

      // Party accounts (credit / debit ledger)
      { path: 'accounts', name: 'accounts', component: () => import('@/pages/accounts/AccountsPage.vue'), meta: { permission: 'party-list' } },

      // Supervisor & site management (field cash purchases + invoice archive)
      { path: 'site/purchases', name: 'purchase-requests', component: () => import('@/pages/supervisor/PurchaseRequestsPage.vue'), meta: { permission: 'purchase-request-list' } },
      { path: 'site/invoices', name: 'site-invoices', component: () => import('@/pages/supervisor/SiteInvoicesPage.vue'), meta: { permission: 'site-invoice-list' } },
      { path: 'site/workers', name: 'workers', component: () => import('@/pages/supervisor/WorkersPage.vue'), meta: { permission: 'worker-list' } },
      { path: 'site/attendance', name: 'field-attendance', component: () => import('@/pages/supervisor/FieldAttendancePage.vue'), meta: { permission: 'worker-attendance-list' } },

      // Reports engine
      { path: 'reports', name: 'reports', component: () => import('@/pages/reports/ReportsPage.vue'), meta: { permission: 'report-list' } },

      // HR settings — Departments & Designations
      { path: 'hr/employees', name: 'employees', component: () => import('@/pages/hr/EmployeesPage.vue'), meta: { permission: 'employee-list' } },
      { path: 'hr/employees/create', name: 'employee-create', component: () => import('@/pages/hr/EmployeeForm.vue'), meta: { permission: 'employee-create' } },
      { path: 'hr/employees/edit/:id', name: 'employee-edit', component: () => import('@/pages/hr/EmployeeForm.vue'), meta: { permission: 'employee-edit' } },
      { path: 'hr/employees/:id', name: 'employee-show', component: () => import('@/pages/hr/EmployeeShow.vue'), meta: { permission: 'employee-list' } },
      { path: 'hr/departments', name: 'departments', component: () => import('@/pages/hr/DepartmentsPage.vue'), meta: { permission: 'department-list' } },
      { path: 'hr/attendance', name: 'attendance', component: () => import('@/pages/hr/AttendancePage.vue'), meta: { permission: 'attendance-list' } },
      { path: 'hr/payroll', name: 'payroll', component: () => import('@/pages/hr/PayrollPage.vue'), meta: { permission: 'payroll-list' } },
      { path: 'hr/leaves', name: 'leaves', component: () => import('@/pages/hr/LeavesPage.vue'), meta: { permission: 'leave-list' } },
      { path: 'hr/designations', name: 'designations', component: () => import('@/pages/hr/DesignationsPage.vue'), meta: { permission: 'designation-list' } },

      // System pages
      { path: 'backup', name: 'backup', component: () => import('@/pages/system/BackupPage.vue'), meta: { permission: 'backup-list' } },
      { path: 'sync', name: 'sync', component: () => import('@/pages/system/SyncPage.vue'), meta: { permission: 'dashboard-list' } },
      { path: 'platform', name: 'platform', component: () => import('@/pages/platform/PlatformPage.vue'), meta: { platform: true } },
      { path: 'log', name: 'log', component: () => import('@/pages/system/LogPage.vue'), meta: { permission: 'log-list' } },
      { path: 'trash', name: 'trash', component: () => import('@/pages/system/TrashPage.vue'), meta: { permission: 'trash-list' } },
      { path: 'notification', name: 'notification', component: () => import('@/pages/system/NotificationPage.vue'), meta: { permission: 'notification-list' } },
      { path: 'theme', name: 'theme', component: () => import('@/pages/system/ThemePage.vue'), meta: { permission: 'theme-list' } },

      // Phase 2 module previews
      { path: 'coming-soon/:module', name: 'coming-soon', component: () => import('@/pages/ComingSoonPage.vue') },

      // Aliases matching the sidebar's legacy-style single-word URLs
      { path: 'user', name: 'user-alias', component: () => import('@/pages/users/UsersPage.vue') },
      { path: 'role', name: 'role-alias', component: () => import('@/pages/roles/RolesPage.vue') },
      { path: 'branch', name: 'branch-alias', component: () => import('@/pages/branches/BranchesPage.vue') }
    ]
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue')
  }
]

export default routes
