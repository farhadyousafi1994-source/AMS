import { menus } from '@/layouts/menus.js'

// The Control Room catalogue. The menu section is derived from the live sidebar
// so every group/sub-menu is controllable. The page catalogue describes every
// configurable element on a page — cards, toolbar actions, table columns and
// form fields — grouped into sections. Pages read the same keys via useUiConfig,
// so adding a new page here (or a new element to a page) makes it instantly
// controllable with no other code changes.

/** All sidebar groups and their sub-menus, as controllable rows. */
export function menuCatalog () {
  return menus
    .filter((m) => m.name !== 'Logout')
    .map((m) => ({
      key: 'menu.' + m.room,
      name: m.name,
      icon: m.icon,
      url: m.url,
      platform: !!m.platform,
      subs: (m.is_sub || []).map((s) => ({ key: 'sub.' + s.room, name: s.name, icon: s.icon, url: s.url })),
    }))
}

/** Find the page catalogue entry for a sidebar url (so a menu item maps to
 *  its configurable components). */
export function pageByRoute (url) {
  if (!url) return null
  return pageCatalog.find((p) => p.route === url) || null
}

// Which controls each section type exposes. A control is a boolean flag stored
// in the element's props (except `visible` = !hidden, and `order` = sort_order).
export const SECTION_CONTROLS = {
  cards: [
    { flag: 'visible', label: 'Visible', icon: 'visibility' },
    { flag: 'expanded', label: 'ExpandedByDefault', icon: 'unfold_more' },
    { flag: 'order', label: 'Order', icon: 'swap_vert' },
  ],
  toolbar: [
    { flag: 'visible', label: 'Visible', icon: 'visibility' },
    { flag: 'disabled', label: 'Disabled', icon: 'block', invert: true },
  ],
  columns: [
    { flag: 'visible', label: 'Visible', icon: 'visibility' },
    { flag: 'sortable', label: 'Sortable', icon: 'sort', on: true },
    { flag: 'order', label: 'Order', icon: 'swap_vert' },
  ],
  fields: [
    { flag: 'visible', label: 'Visible', icon: 'visibility' },
    { flag: 'required', label: 'Required', icon: 'star' },
    { flag: 'readonly', label: 'ReadOnly', icon: 'lock' },
    { flag: 'order', label: 'Order', icon: 'swap_vert' },
  ],
}

const SECTION_META = {
  cards: { label: 'Cards', icon: 'dashboard' },
  toolbar: { label: 'ToolbarActions', icon: 'build' },
  columns: { label: 'TableColumns', icon: 'view_column' },
  fields: { label: 'FormFields', icon: 'edit_note' },
}
export function sectionMeta (type) { return SECTION_META[type] || { label: type, icon: 'tune' } }

// Reasonable icons per column name (falls back to a generic row icon).
const COL_ICON = {
  code: 'tag', name: 'label', full_name: 'person', client_name: 'person', type: 'category', category: 'category',
  contract_value: 'payments', progress: 'percent', status: 'flag', active: 'flag', department: 'apartment',
  designation: 'badge', employment_type: 'work', basic_salary: 'payments', available: 'check_circle', allocated: 'domain',
  branch: 'store', location: 'place', tracking: 'straighten', expense_date: 'event', invoice_date: 'event',
  receipt_date: 'event', order_date: 'event', project: 'domain', payee: 'person', payer: 'person', amount: 'payments',
  rate: 'currency_exchange', amount_base: 'account_balance_wallet', paid_base: 'account_balance_wallet',
  balance_base: 'account_balance_wallet', balance: 'account_balance_wallet', paid: 'account_balance_wallet', total: 'payments',
  invoice_no: 'tag', receipt_no: 'tag', method: 'payments', quantity: 'inventory', min_quantity: 'warning',
  movements_count: 'swap_vert', supplier: 'local_shipping', phone: 'phone', purchase_orders_count: 'shopping_cart',
  father_name: 'family_restroom', trade: 'construction', attendances_count: 'event_available', email: 'email',
  roles: 'rule', projects: 'domain', collect: 'payments', invoice: 'receipt_long',
}

/** Shorthand for a list page whose only controls are its table columns. */
function colsPage (page, name, icon, route, cols) {
  return {
    page, name, icon, route,
    sections: { columns: cols.map(([n, label]) => ({ key: `page.${page}.col.${n}`, name: label, icon: COL_ICON[n] || 'table_rows' })) },
  }
}

export const pageCatalog = [
  {
    page: 'treasury', name: 'GeneralBudget', icon: 'savings', route: '/finance/treasury',
    sections: {
      cards: [
        { key: 'page.treasury.card.available', name: 'Available', icon: 'account_balance' },
        { key: 'page.treasury.card.reserved', name: 'Reserved', icon: 'lock_clock' },
        { key: 'page.treasury.card.total', name: 'TotalBalance', icon: 'savings' },
        { key: 'page.treasury.card.allocated', name: 'AllocatedToProjects', icon: 'domain' },
      ],
      toolbar: [
        { key: 'page.treasury.action.add', name: 'AddTransaction', icon: 'add' },
        { key: 'page.treasury.action.export', name: 'Export', icon: 'download' },
      ],
      columns: [
        { key: 'page.treasury.col.date', name: 'Date', icon: 'event' },
        { key: 'page.treasury.col.kind', name: 'Kind', icon: 'swap_vert' },
        { key: 'page.treasury.col.project', name: 'Project', icon: 'domain' },
        { key: 'page.treasury.col.money_in', name: 'MoneyIn', icon: 'south_west' },
        { key: 'page.treasury.col.money_out', name: 'MoneyOut', icon: 'north_east' },
        { key: 'page.treasury.col.status', name: 'Status', icon: 'flag' },
        { key: 'page.treasury.col.notes', name: 'Notes', icon: 'notes' },
      ],
      fields: [
        { key: 'page.treasury.field.kind', name: 'Kind', icon: 'swap_vert' },
        { key: 'page.treasury.field.amount', name: 'Amount', icon: 'payments' },
        { key: 'page.treasury.field.date', name: 'Date', icon: 'event' },
        { key: 'page.treasury.field.project', name: 'Project', icon: 'domain' },
        { key: 'page.treasury.field.note', name: 'Notes', icon: 'notes' },
      ],
    },
  },
  {
    page: 'investors', name: 'Investors', icon: 'diamond', route: '/investors',
    sections: {
      columns: [
        { key: 'page.investors.col.code', name: 'Code', icon: 'tag' },
        { key: 'page.investors.col.name', name: 'Name', icon: 'person' },
        { key: 'page.investors.col.type', name: 'InvestorType', icon: 'category' },
        { key: 'page.investors.col.phone', name: 'Phone', icon: 'phone' },
        { key: 'page.investors.col.investments_count', name: 'ProjectsCount', icon: 'domain' },
        { key: 'page.investors.col.total_capital', name: 'TotalCapital', icon: 'savings' },
        { key: 'page.investors.col.total_profit', name: 'ProfitReceived', icon: 'trending_up' },
      ],
    },
  },
  {
    page: 'subcontractors', name: 'Subcontractors', icon: 'engineering', route: '/subcontractors',
    sections: {
      columns: [
        { key: 'page.subcontractors.col.code', name: 'Code', icon: 'tag' },
        { key: 'page.subcontractors.col.name', name: 'Name', icon: 'person' },
        { key: 'page.subcontractors.col.trade', name: 'Trade', icon: 'construction' },
        { key: 'page.subcontractors.col.phone', name: 'Phone', icon: 'phone' },
        { key: 'page.subcontractors.col.projects_count', name: 'Projects', icon: 'domain' },
        { key: 'page.subcontractors.col.contract_total', name: 'ContractTotal', icon: 'description' },
        { key: 'page.subcontractors.col.paid_total', name: 'Paid', icon: 'payments' },
        { key: 'page.subcontractors.col.balance', name: 'Balance', icon: 'account_balance_wallet' },
        { key: 'page.subcontractors.col.rating_avg', name: 'Rating', icon: 'star' },
        { key: 'page.subcontractors.col.fingerprint_id', name: 'Fingerprint', icon: 'fingerprint' },
      ],
    },
  },
  {
    page: 'contracts', name: 'Contracts', icon: 'handshake', route: '/contracts',
    sections: {
      columns: [
        { key: 'page.contracts.col.code', name: 'Code', icon: 'tag' },
        { key: 'page.contracts.col.title', name: 'Title', icon: 'title' },
        { key: 'page.contracts.col.party_name', name: 'PartyName', icon: 'person' },
        { key: 'page.contracts.col.direction', name: 'Direction', icon: 'swap_horiz' },
        { key: 'page.contracts.col.party_type', name: 'PartyType', icon: 'groups' },
        { key: 'page.contracts.col.amount', name: 'ContractAmount', icon: 'payments' },
        { key: 'page.contracts.col.balance', name: 'Balance', icon: 'account_balance_wallet' },
        { key: 'page.contracts.col.status', name: 'Status', icon: 'flag' },
      ],
    },
  },
  colsPage('employees', 'Employees', 'groups', '/hr/employees', [
    ['code', 'Code'], ['full_name', 'FullName'], ['department', 'Department'], ['designation', 'Designation'],
    ['employment_type', 'EmploymentType'], ['basic_salary', 'Salary'], ['status', 'Status'],
  ]),
  colsPage('assets', 'Assets', 'construction', '/assets', [
    ['code', 'Code'], ['name', 'AssetName'], ['category', 'Category'], ['tracking', 'TrackingMode'],
    ['available', 'Available'], ['allocated', 'Allocated'], ['branch', 'Branch'], ['status', 'Status'], ['location', 'Location'],
  ]),
  colsPage('expenses', 'Expenses', 'receipt_long', '/finance/expenses', [
    ['expense_date', 'ExpenseDate'], ['category', 'Category'], ['project', 'Project'], ['payee', 'Payee'],
    ['amount', 'Amount'], ['rate', 'Rate'], ['amount_base', 'BaseAmount'],
  ]),
  colsPage('invoices', 'Invoices', 'request_quote', '/finance/invoices', [
    ['invoice_no', 'InvoiceNo'], ['invoice_date', 'InvoiceDate'], ['client_name', 'Client'], ['project', 'Project'],
    ['total', 'Total'], ['paid_base', 'Paid'], ['balance_base', 'Balance'], ['status', 'Status'], ['collect', 'Receipts'],
  ]),
  colsPage('receipts', 'Receipts', 'payments', '/finance/receipts', [
    ['receipt_no', 'ReceiptNo'], ['receipt_date', 'PaymentDate'], ['payer', 'Payer'], ['invoice', 'InvoiceNo'],
    ['amount', 'Amount'], ['amount_base', 'BaseAmount'], ['method', 'Method'],
  ]),
  colsPage('stock', 'Warehouse', 'inventory', '/procurement/stock', [
    ['code', 'Code'], ['name', 'Name'], ['quantity', 'OnHand'], ['min_quantity', 'MinQuantity'], ['movements_count', 'Movements'],
  ]),
  colsPage('purchaseOrders', 'PurchaseOrders', 'shopping_cart', '/procurement/purchase-orders', [
    ['code', 'Code'], ['supplier', 'Supplier'], ['project', 'Project'], ['order_date', 'Date'], ['total', 'Total'], ['status', 'Status'],
  ]),
  colsPage('suppliers', 'Suppliers', 'local_shipping', '/procurement/suppliers', [
    ['code', 'Code'], ['name', 'Name'], ['category', 'Category'], ['phone', 'Phone'], ['purchase_orders_count', 'PurchaseOrders'],
  ]),
  colsPage('workers', 'Workers', 'badge', '/site/workers', [
    ['code', 'Code'], ['name', 'Name'], ['father_name', 'FatherName'], ['trade', 'Trade'], ['project', 'Project'],
    ['attendances_count', 'Days'], ['active', 'Status'],
  ]),
  colsPage('users', 'User', 'manage_accounts', '/user', [
    ['name', 'Name'], ['email', 'Email'], ['roles', 'Roles'], ['projects', 'AssignedProjects'],
  ]),
  colsPage('purchaseRequests', 'PurchaseRequests', 'shopping_basket', '/site/purchases', [
    ['code', 'Code'], ['title', 'Title'], ['project', 'Project'], ['status', 'Status'],
    ['estimated_total', 'EstimatedTotal'], ['reconcile', 'SpentVsAdvanced'],
  ]),
  colsPage('changeOrders', 'ChangeOrders', 'published_with_changes', '/change-orders', [
    ['code', 'Code'], ['title', 'Title'], ['project', 'Project'], ['kind', 'Kind'],
    ['cost_impact_base', 'CostImpact'], ['time_impact_days', 'Days'], ['status', 'Status'],
  ]),
  colsPage('homeExpenses', 'HomeExpenses', 'home', '/finance/home-expenses', [
    ['expense_date', 'Date'], ['category', 'Category'], ['payment_method', 'PaymentMethod'],
    ['amount_base', 'Amount'], ['attach', 'Receipt'],
  ]),
  colsPage('officeExpenses', 'OfficeExpenses', 'business_center', '/finance/office-expenses', [
    ['expense_date', 'Date'], ['category', 'Category'], ['vendor', 'Vendor'], ['payment_method', 'PaymentMethod'],
    ['amount_base', 'Amount'], ['attach', 'Receipt'], ['approval_status', 'Status'],
  ]),
  colsPage('incidents', 'SafetyIncidents', 'report', '/safety/incidents', [
    ['code', 'Code'], ['title', 'Title'], ['project', 'Project'], ['type', 'Type'],
    ['severity', 'Severity'], ['incident_date', 'Date'], ['status', 'Status'],
  ]),
  colsPage('roles', 'UserRole', 'rule', '/role', [
    ['name', 'RoleName'], ['modules_count', 'Modules'], ['permissions_count', 'Permissions'], ['users_count', 'AssignedUsers'],
  ]),
  colsPage('branches', 'Branch', 'store', '/branch', [
    ['name', 'Name'], ['address', 'Address'], ['phone', 'Phone'], ['active', 'Status'],
  ]),
  colsPage('accounts', 'PartyAccounts', 'account_balance', '/accounts', [
    ['code', 'Code'], ['name', 'Name'], ['type', 'PartyType'], ['phone', 'Phone'], ['transactions_count', 'Transactions'],
    ['in_total', 'MoneyIn'], ['out_total', 'MoneyOut'], ['balance', 'Balance'], ['pending_total', 'Pending'],
  ]),
  {
    page: 'projects', name: 'Projects', icon: 'domain', route: '/projects',
    sections: {
      columns: [
        { key: 'page.projects.col.code', name: 'Code', icon: 'tag' },
        { key: 'page.projects.col.name', name: 'ProjectName', icon: 'domain' },
        { key: 'page.projects.col.client_name', name: 'Client', icon: 'person' },
        { key: 'page.projects.col.type', name: 'Type', icon: 'category' },
        { key: 'page.projects.col.contract_value', name: 'ContractValue', icon: 'payments' },
        { key: 'page.projects.col.progress', name: 'Progress', icon: 'percent' },
        { key: 'page.projects.col.status', name: 'Status', icon: 'flag' },
      ],
      fields: [
        { key: 'page.projects.input.name_fa', name: 'ProjectNameFa', icon: 'translate' },
        { key: 'page.projects.input.code', name: 'Code', icon: 'tag' },
        { key: 'page.projects.input.client_name', name: 'Client', icon: 'person' },
        { key: 'page.projects.input.branch', name: 'Branch', icon: 'store' },
        { key: 'page.projects.input.type', name: 'Type', icon: 'category' },
        { key: 'page.projects.input.status', name: 'Status', icon: 'flag' },
        { key: 'page.projects.input.description', name: 'Description', icon: 'notes' },
        { key: 'page.projects.input.map', name: 'PinOnMap', icon: 'pin_drop' },
      ],
      tabs: [
        { key: 'page.projects.tab.financing', name: 'Financing', icon: 'account_balance' },
        { key: 'page.projects.tab.wbs', name: 'WorkBreakdown', icon: 'checklist' },
        { key: 'page.projects.tab.sites', name: 'SiteOperations', icon: 'place' },
        { key: 'page.projects.tab.plant', name: 'PlantMaterials', icon: 'construction' },
        { key: 'page.projects.tab.docs', name: 'DrawingsDocs', icon: 'folder' },
      ],
      toolbar: [
        { key: 'page.projects.table.advanced_search', name: 'AdvanceSearch', icon: 'manage_search' },
      ],
    },
  },
]

// tabs behave like toolbar for control purposes (visible/enabled).
SECTION_CONTROLS.tabs = SECTION_CONTROLS.toolbar
SECTION_META.tabs = { label: 'Tabs', icon: 'tab' }

/** The tab layout of the Control Room itself. */
export const controlTabs = [
  { name: 'menus', label: 'SidebarMenus', icon: 'menu' },
  { name: 'pages', label: 'PagesAndComponents', icon: 'tune' },
]
