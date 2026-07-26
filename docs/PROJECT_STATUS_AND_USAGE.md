# Aria Herat ERP — Status & Usage Guide

_Last updated: after the General Budget treasury, Party Accounts (credit/
debit ledger), project creation wizard, redesigned project dashboard,
role-based feature visibility, and system-wide multi-currency clarity._

This document answers two questions:
1. **How much of the client's requirements is done?** (with an honest breakdown)
2. **What can you use today, and how?** (a step-by-step usage guide)

---

## 1. Completion vs. the proposal

The client proposal (Nova Technologies, ref **NT26-331**) defines **5 priced areas**.
The percentages below are weighted by the proposal's own module prices (which
reflect complexity/effort), so the overall number is realistic — not inflated.

| Area (proposal) | Price weight | Status | Done |
|---|---|---|---|
| **Core** — Platform & Executive Dashboard (ESS) | $410 | Mostly done | **~90%** |
| **Part A** — Projects & Sites | $620 | **Complete** | **100%** |
| **Part B** — Finance & Accounting | $560 | In progress | **~75%** |
| **Part C** — Procurement, Inventory & Assets | $430 | In progress | **~45%** |
| **Part D** — HR & Payroll | $380 | In progress | **~45%** |
| **Total** | **$2,400** | — | **≈ 76%** |

**Overall completion: about 76%.**

Added since the last revision: the **General Budget treasury** (deposits,
project allocations that deduct automatically from the company's cap-table
share, project receipts held **reserved** until completion then released),
**Party Accounts (حسابات)** — a credit/debit ledger for lenders, banks,
exchanges and relatives outside the cap table, with running balances,
receipt-image attachments and printable statements — a **6-step project
creation wizard**, a fully **redesigned project dashboard** (floating pill
sections, live activity feed, arc gauges, embedded map, visible document
thumbnails, printable A-to-Z report, AFN/USD switcher at the daily rate),
**role-controlled feature visibility** (Offline, Theme, Notifications,
Shortcuts, Log, Backup and each interface language can be hidden per role),
**system-wide multi-currency clarity** (every amount labelled, per-currency
breakdowns, auto-locked daily rates in every money form), the two financial
reports (**General Budget** and **Party Accounts**) in the reports engine,
and a **live main dashboard** fed by real finance/HR/asset figures.

On top of the five priced areas, three cross-cutting modules the client asked
for in the follow-up brief are now built end-to-end: **Investors & Cap Table**,
**Subcontractor Contracts** (standalone, cross-project), and a **Reports engine**
with PDF / Excel / Word export.

### The non-negotiable business rules — all honoured
- **Capital amount and profit-share % are independent** on every cap-table row —
  never derived from each other. An **underfunded project is allowed and flagged**
  by a live funding meter, not blocked.
- **The company itself is a participant** in every project's cap table
  (`is_company` flag, cached participant name).
- **Assets are returnable** (allocate → return) and tracked either **per-unit**
  (own serial + maintenance history) or **by-count** (total → allocated →
  available). Stock/consumables share the same total→allocated→available logic.
- **Investor = a person/company created once**; an **investment = a link inside a
  project's cap table**. One investor rolls up across all their projects.
- **Multi-currency AFN + USD with a locked rate** on every money field.

### Part A is 100%
All 7 proposal items: project/contract records, **work breakdown & tasks**
(phases + team assignment), progress & milestones, site management, daily site
logs, subcontractors + settlement, and drawings & documents. Projects now also
carry a **location**, an **8-stage lifecycle** (planning → awaiting funding →
active → on hold → near completion → completed → handover → cancelled) kept
separate from physical **progress %**, and a **Cap Table** tab.

### Part B — what’s done (~75%)
The **currency + daily exchange-rate engine with rate-lock**; **Expenses** with
categories; **client Invoicing** (line items, discount/tax, status, progress
billing); **Receipts** that auto-update the invoice's paid balance; and now a
**Profit & Loss report** plus a first-class **Cap Table / investor statement**.
Now also live: the **General Budget treasury**, **Party Accounts (حسابات)** credit/debit ledger, and multi-currency clarity with auto-locked daily rates. Remaining: budgets vs. actual cost, cash & bank accounts, journal & ledger.

### Part C — what's done (~45%)
**Assets** are complete: heavy machinery, vehicles, tools and equipment, tracked
per-unit or by-count, with allocation, condition, location and a **maintenance
log** on flagship machines. A **Stock & Assets report** is available. Remaining:
purchase orders, suppliers, and warehouse stock-movement history.

### Part D — what's done (~45%)
**Departments & Designations** (full Persian hierarchy), **Employees** (A–Z
personal / employment / payroll record, department-filtered designations,
cross-module vehicle & manager links), and a **Payroll report** (basic +
allowances → gross). Remaining: attendance & leave, payroll processing runs,
overtime/bonus/deductions, and printable salary slips.

### Why Core is ~90% and not 100%
Done: login, navbar, sidebar, users & roles, multi-language (EN/FA), branding,
audit log, trash, notifications, theme, search, multi-branch, multi-company,
offline-first sync, and now the **Reports engine**. Remaining: real automatic
backups (working stub today) and the full owner ESS dashboard with live
cross-division financials once every division is feeding it.

---

## 2. What is done (module by module)

### Platform (Core)
- **Login** — branded page, offline indicator, one-click demo login.
- **Navbar** — company name, search hint (`Ctrl+K`), fullscreen, sync status,
  notifications, shortcuts, language switcher, user menu, live clock.
- **Sidebar** — searchable menu, branch switcher, permission-gated items.
- **Dashboard** — construction KPIs, business-entity chips, quick actions,
  recent activity feed.
- **Users & Roles** — full CRUD, permission-matrix RBAC.
- **Branches / Multi-Company** — multi-branch CRUD; create/switch companies.
- **Theme**, **Notifications**, **Activity Log**, **Trash**, **Backup** (stub),
  **Offline & Sync**.

### Part A — Projects & Sites (complete)
Overview header (client, location, type, contract value, 8-stage status, live
progress) plus tabs: **Cap Table**, **Tasks (work breakdown)**, **Sites**,
**Milestones**, **Daily Logs**, **Subcontractors + settlement**, **Documents**.

### Investors & Cap Table (cross-cutting)
- **Investors** page — a person/company created once (individual / company /
  government), with a **cross-project history** of every investment and totals.
- **Cap Table** tab on each project — two **live funding meters** (capital raised
  vs. target with the gap flagged, and profit % allocated vs. 100%); add the
  company itself or any investor with an **independent** capital and profit %.
- Demo seeded: the **Herat football stadium** (USD 100M, 45M raised, 55M gap,
  80% profit allocated) with the company as lead participant.

### Subcontractor Contracts (standalone, cross-project)
Contracts with a **direction** (client = money in / subcontractor = money out),
**party type** (individual / company / government), milestones, a payments panel
with running **settlement**, and a **cross-project history** for each party.

### Part C — Assets
Per-unit and by-count tracking, allocation, condition, location, maintenance log,
and category/status filters. Full Persian asset pool seeded.

### Part D — HR
**Departments**, **Designations**, and **Employees** (sectioned personal /
employment / payroll form).

### Reports engine
Seven reports on one engine with shared date-range + project filters and
**PDF / Excel / Word** export: Executive Overview, Profit & Loss, Project Report,
**Cap Table & Investor statement**, Stock & Assets, Payroll, and Approval Log.

---

## 3. How to use it

### First-time setup (Windows)
```cmd
:: Backend
cd backend
copy .env.example .env
type nul > database\database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve

:: Frontend (second terminal)
cd frontend
pnpm install
pnpm dev
```
Open **http://localhost:9000** and sign in with **admin@ariaherat.af / password**
(or click the **Administrator** demo button).

> Already set up before? Re-run `php artisan migrate --seed` after pulling to add
> the new tables, permissions and sample data (investors, cap table, contracts).

### Daily use

**Projects & the Cap Table**
1. Sidebar → **Projects & Sites** → **Add New** (name, code, client, location,
   type, contract value, currency, dates, status, progress).
2. Open a project → **Cap Table** tab. The two meters show funding vs. target and
   profit allocated. **Add New** to add the company or an investor with a capital
   amount **and** a profit % (they are independent). Underfunding is allowed and
   shown as a gap.
3. Other tabs: Tasks, Sites, Milestones, Daily Logs, Subcontractors, Documents.

**Investors**
- Sidebar → **Investors** → **Add New** to register a person/company once.
- Click an investor's name to see their **cross-project history** and totals.

**Subcontractor Contracts**
- Sidebar → **Contracts** → **Add New** (title, party, direction, project,
  amount, currency, dates). Click a contract to manage **Milestones**,
  **Payments** (with live settlement), and see its **cross-project history**.

**Assets**
- Sidebar → **Procurement & Assets → Assets**. Filter by category/status; add
  per-unit or by-count assets; open one to log maintenance.

**HR**
- Sidebar → **HR & Payroll** → **Departments**, **Designations**, **Employees**.

**Reports**
- Sidebar → **Reports** → pick a report tile, set the date range / project, then
  **Run Report**. Export any result as **PDF**, **Excel**, or **Word**.

**Users, roles & access**
- **Control Panel → Users / Roles** — all new permissions (`investor-*`,
  `investment-*`, `contract-*`, `asset-*`, `employee-*`, `report-*`, …) appear
  in the role matrix.

**System tools**
- **Theme**, **Notifications**, **Activity Log** (now covers every module),
  **Trash**, **Backup** (stub), **Offline & Sync**.

---

## 4. Not built yet (so expectations are clear)

- **Part B remainder**: budgets vs. actual cost, cash & bank accounts, journal &
  ledger.
- **Part C remainder**: purchase orders, suppliers, warehouse stock-movement.
- **Part D remainder**: attendance & leave, payroll processing runs,
  overtime/bonus/deductions, printable salary slips.
- **Core remainder**: automatic backups, and the full owner ESS dashboard with
  live cross-division financials.

Every money record already carries a **locked exchange rate**, so the remaining
finance features plug straight into the existing rate-lock engine.
