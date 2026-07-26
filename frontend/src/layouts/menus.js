// Standard ERP menu: Dashboard, then domain groups in workflow order
// (Projects → Site → Finance → Partners → Procurement → HR → Reports),
// followed by Administration and System. Every leaf keeps its own permission
// so role visibility is unchanged; empty groups collapse automatically.
export const menus = [
  {
    room: 'Dashboard', permission: 'dashboard-list', icon: 'mdi-monitor-dashboard',
    name: 'Dashboard', status: true, color: 'blue', url: '/', is_sub: []
  },

  // VIP Control Center — Platform Owner only (gated by is_platform_owner, not a permission)
  {
    room: 'PlatformControl', platform: true, permission: null, icon: 'mdi-shield-crown',
    name: 'VIPControlCenter', status: true, color: 'amber', url: '/platform', is_sub: []
  },

  // ── Projects ──────────────────────────────────────────────────────────
  {
    room: 'ProjectsGroup', permission: 'project-list', icon: 'domain',
    name: 'Projects', status: true, color: 'blue-grey', url: null,
    is_sub: [
      { room: 'ProjectsSites', permission: 'project-list', icon: 'domain', name: 'ProjectsAndSites', status: true, color: 'black', url: '/projects', add_url: '/projects/create', is_sub: [] },
      { room: 'ChangeOrders', permission: 'change-order-list', icon: 'published_with_changes', name: 'ChangeOrders', status: true, color: 'black', url: '/change-orders', add_url: null, is_sub: [] }
    ]
  },

  // ── Site Management (field operations) ────────────────────────────────
  {
    room: 'SiteManagement', permission: 'purchase-request-list', icon: 'engineering',
    name: 'SiteManagement', status: true, color: 'deep-orange', url: null,
    is_sub: [
      { room: 'PurchaseRequests', permission: 'purchase-request-list', icon: 'shopping_basket', name: 'PurchaseRequests', status: true, color: 'black', url: '/site/purchases', add_url: null, is_sub: [] },
      { room: 'FieldAttendance', permission: 'worker-attendance-list', icon: 'event_available', name: 'FieldAttendance', status: true, color: 'black', url: '/site/attendance', add_url: null, is_sub: [] },
      { room: 'Workers', permission: 'worker-list', icon: 'badge', name: 'Workers', status: true, color: 'black', url: '/site/workers', add_url: null, is_sub: [] },
      { room: 'InvoiceArchive', permission: 'site-invoice-list', icon: 'photo_library', name: 'InvoiceArchive', status: true, color: 'black', url: '/site/invoices', add_url: null, is_sub: [] }
    ]
  },

  // ── Finance & Accounting ──────────────────────────────────────────────
  {
    room: 'FinanceAccounting', permission: 'expense-list', icon: 'account_balance_wallet',
    name: 'FinanceAndAccounting', status: true, color: 'green', url: null,
    is_sub: [
      { room: 'PaymentCenter', permission: 'payment-request-list', icon: 'account_balance', name: 'PaymentCenter', status: true, color: 'black', url: '/finance/payment-center', add_url: null, is_sub: [] },
      { room: 'GeneralBudget', permission: 'treasury-list', icon: 'savings', name: 'GeneralBudget', status: true, color: 'black', url: '/finance/treasury', add_url: null, is_sub: [] },
      { room: 'PartyAccounts', permission: 'party-list', icon: 'account_balance', name: 'PartyAccounts', status: true, color: 'black', url: '/accounts', add_url: null, is_sub: [] },
      { room: 'Shareholders', permission: 'partner-list', icon: 'diamond', name: 'Shareholders', status: true, color: 'black', url: '/finance/shareholders', add_url: null, is_sub: [] },
      { room: 'Invoices', permission: 'invoice-list', icon: 'request_quote', name: 'Invoices', status: true, color: 'black', url: '/finance/invoices', add_url: null, is_sub: [] },
      { room: 'Receipts', permission: 'receipt-list', icon: 'payments', name: 'Receipts', status: true, color: 'black', url: '/finance/receipts', add_url: null, is_sub: [] },
      { room: 'Expenses', permission: 'expense-list', icon: 'receipt_long', name: 'Expenses', status: true, color: 'black', url: '/finance/expenses', add_url: null, is_sub: [] },
      { room: 'OfficeExpenses', permission: 'office-expense-list', icon: 'business_center', name: 'OfficeExpenses', status: true, color: 'black', url: '/finance/office-expenses', add_url: null, is_sub: [] },
      { room: 'HomeExpenses', permission: 'home-expense-list', icon: 'home', name: 'HomeExpenses', status: true, color: 'black', url: '/finance/home-expenses', add_url: null, is_sub: [] },
      { room: 'ExchangeRates', permission: 'exchange-rate-list', icon: 'currency_exchange', name: 'ExchangeRates', status: true, color: 'black', url: '/finance/exchange-rates', add_url: null, is_sub: [] },
      { room: 'Currencies', permission: 'currency-list', icon: 'attach_money', name: 'Currencies', status: true, color: 'black', url: '/finance/currencies', add_url: null, is_sub: [] }
    ]
  },

  // ── Partners & Contracts (stakeholders) ───────────────────────────────
  {
    room: 'PartnersContracts', permission: 'investor-list', icon: 'handshake',
    name: 'PartnersAndContracts', status: true, color: 'indigo', url: null,
    is_sub: [
      { room: 'Investors', permission: 'investor-list', icon: 'diversity_3', name: 'Investors', status: true, color: 'black', url: '/investors', add_url: null, is_sub: [] },
      { room: 'Subcontractors', permission: 'tradesman-list', icon: 'engineering', name: 'Subcontractors', status: true, color: 'black', url: '/subcontractors', add_url: null, is_sub: [] },
      { room: 'Contracts', permission: 'contract-list', icon: 'assignment_turned_in', name: 'Contracts', status: true, color: 'black', url: '/contracts', add_url: null, is_sub: [] }
    ]
  },

  // ── Procurement & Assets ──────────────────────────────────────────────
  {
    room: 'ProcurementAssets', permission: 'asset-list', icon: 'local_shipping',
    name: 'ProcurementAndAssets', status: true, color: 'brown', url: null,
    is_sub: [
      { room: 'PurchaseOrders', permission: 'purchase-order-list', icon: 'shopping_cart', name: 'PurchaseOrders', status: true, color: 'black', url: '/procurement/purchase-orders', add_url: null, is_sub: [] },
      { room: 'Suppliers', permission: 'supplier-list', icon: 'local_shipping', name: 'Suppliers', status: true, color: 'black', url: '/procurement/suppliers', add_url: null, is_sub: [] },
      { room: 'Warehouse', permission: 'stock-item-list', icon: 'inventory', name: 'Warehouse', status: true, color: 'black', url: '/procurement/stock', add_url: null, is_sub: [] },
      { room: 'Assets', permission: 'asset-list', icon: 'construction', name: 'Assets', status: true, color: 'black', url: '/assets', add_url: null, is_sub: [] }
    ]
  },

  // ── HR & Payroll ──────────────────────────────────────────────────────
  {
    room: 'HRPayroll', permission: 'department-list', icon: 'badge',
    name: 'HRAndPayroll', status: true, color: 'teal', url: null,
    is_sub: [
      { room: 'Employees', permission: 'employee-list', icon: 'groups', name: 'Employees', status: true, color: 'black', url: '/hr/employees', add_url: '/hr/employees/create', is_sub: [] },
      { room: 'Attendance', permission: 'attendance-list', icon: 'event_available', name: 'Attendance', status: true, color: 'black', url: '/hr/attendance', add_url: null, is_sub: [] },
      { room: 'PayrollRuns', permission: 'payroll-list', icon: 'payments', name: 'PayrollRuns', status: true, color: 'black', url: '/hr/payroll', add_url: null, is_sub: [] },
      { room: 'Leaves', permission: 'leave-list', icon: 'beach_access', name: 'LeaveManagement', status: true, color: 'black', url: '/hr/leaves', add_url: null, is_sub: [] },
      { room: 'Departments', permission: 'department-list', icon: 'apartment', name: 'Departments', status: true, color: 'black', url: '/hr/departments', add_url: null, is_sub: [] },
      { room: 'Designations', permission: 'designation-list', icon: 'badge', name: 'Designations', status: true, color: 'black', url: '/hr/designations', add_url: null, is_sub: [] }
    ]
  },

  // ── HSE / Safety & Quality ────────────────────────────────────────────
  {
    room: 'SafetyQuality', permission: 'incident-list', icon: 'health_and_safety',
    name: 'SafetyAndQuality', status: true, color: 'red', url: null,
    is_sub: [
      { room: 'SafetyIncidents', permission: 'incident-list', icon: 'report', name: 'SafetyIncidents', status: true, color: 'black', url: '/safety/incidents', add_url: null, is_sub: [] }
    ]
  },

  // ── Reports ───────────────────────────────────────────────────────────
  { room: 'Reports', permission: 'report-list', icon: 'assessment', name: 'Reports', status: true, color: 'indigo', url: '/reports', add_url: null, is_sub: [] },

  // ── Administration (users, roles, branches) ───────────────────────────
  {
    room: 'Administration', permission: 'user-list', icon: 'admin_panel_settings',
    name: 'Administration', status: true, color: 'blue-grey', url: null,
    is_sub: [
      { room: 'User', permission: 'user-list', icon: 'manage_accounts', name: 'User', status: true, color: 'black', url: '/user', add_url: null, is_sub: [] },
      { room: 'Role', permission: 'role-list', icon: 'rule', name: 'UserRole', status: true, color: 'black', url: '/role', add_url: null, is_sub: [] },
      { room: 'Branch', permission: 'branch-list', icon: 'store', name: 'Branch', status: true, color: 'black', url: '/branch', add_url: null, is_sub: [] },
      { room: 'Lookup', permission: 'lookup-list', icon: 'tune', name: 'OptionsRegistry', status: true, color: 'black', url: '/options', add_url: null, is_sub: [] },
      { room: 'ControlRoom', permission: 'ui-setting-list', icon: 'dashboard_customize', name: 'ControlRoom', status: true, color: 'black', url: '/control-room', add_url: null, is_sub: [] }
    ]
  },

  // ── System (utilities & preferences) ──────────────────────────────────
  {
    room: 'System', permission: 'dashboard-list', icon: 'settings_suggest',
    name: 'System', status: true, color: 'blue', url: null,
    is_sub: [
      { room: 'Notification', permission: 'notification-list', icon: 'notifications', name: 'Notification', status: true, color: 'black', url: '/notification', add_url: null, is_sub: [] },
      { room: 'SyncCenter', permission: 'dashboard-list', icon: 'sync', name: 'SyncCenter', status: true, color: 'black', url: '/sync', add_url: null, is_sub: [] },
      { room: 'Backup', permission: 'backup-list', icon: 'backup', name: 'Backup', status: true, color: 'black', url: '/backup', add_url: null, is_sub: [] },
      { room: 'Log', permission: 'log-list', icon: 'visibility', name: 'Log', status: true, color: 'black', url: '/log', add_url: null, is_sub: [] },
      { room: 'Trashes', permission: 'trash-list', icon: 'auto_delete', name: 'Trashes', status: true, color: 'black', url: '/trash', add_url: null, is_sub: [] },
      { room: 'Theme', permission: 'theme-list', icon: 'palette', name: 'ThemeAppearance', status: true, color: 'black', url: '/theme', add_url: null, is_sub: [] },
      { room: 'Fingerprint', permission: 'fingerprint-list', icon: 'fingerprint', name: 'FingerprintSettings', status: true, color: 'black', url: '/fingerprint', add_url: null, is_sub: [] }
    ]
  },

  { room: 'Templates', permission: 'dashboard-list', icon: 'mdi-logout', name: 'Logout', status: true, color: 'blue', url: '', is_sub: [] }
]
