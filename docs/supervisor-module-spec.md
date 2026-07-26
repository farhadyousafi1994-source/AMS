# Supervisor & site management module — feature spec

Reference this file when working on the supervisor/site-management feature
(`@docs/supervisor-module-spec.md`). It is intentionally kept out of the
always-loaded `CLAUDE.md`; that file just points here.

---

## 1. Context

A construction company runs several concurrent projects. On each site a **supervisor**
handles purchasing, day-labor attendance, hiring, and equipment rental. The company
needs a **mobile-first web app** (the supervisor works from the browser on his phone)
backed by a central database the office can see.

Operating constraints that shape the design:

- Low-end Android phones, patchy connectivity → capture-then-sync, not always-online.
- Cash economy, purchases at local shops → no supplier accounts; cash advances.
- Currency is Afghani (AFN). UI language is Dari (RTL); plan for i18n from day one.
- Receipts are often handwritten → do not depend on OCR; supervisor types the total.

## 2. Core principle: trust and verification

Every action the supervisor takes produces **proof** tied to a record:

- Money spent → a receipt/bill (mandatory), linked to an approved request.
- A worker present → a photo + GPS location for that day.
- A worker hired → identity record (name, father's name, photo, generated ID).
- Equipment rented → a rental invoice.

The whole value of the system is that every Afghani and every laborer traces back to a
verifiable record. Design the data model so nothing exists without its evidence.

## 3. Roles

- **Supervisor** — creates requests, records attendance, registers workers, uploads
  receipts, arranges rentals. Field user, phone browser.
- **Site / project engineer** — approves or rejects purchase requests; reviews records.
- **Office / admin** — releases cash, sees all projects, retrieves archived invoices.

"Supervisor" is a **role**, not a menu. There is one set of feature pages (Purchases,
Attendance, Workers, Rentals, Invoices, Reports); the role decides which pages a user
sees and what they can do on each. All three roles open the same app.

### 3.1 Role & permissions matrix

Scope: supervisor and engineer are limited to their **assigned projects**; admin sees
**all projects**.

| Capability | Supervisor | Engineer | Admin |
|---|:---:|:---:|:---:|
| Create purchase request | ✓ | – | ✓ |
| Approve / reject request | – | ✓ | ✓ |
| Release cash advance | – | – | ✓ |
| Upload purchase receipt | ✓ | – | ✓ |
| Record daily attendance | ✓ | – | – |
| Register a worker | ✓ | – | – |
| Verify / view workers | – | ✓ | ✓ |
| Arrange rental + upload invoice | ✓ | – | ✓ |
| Search / filter invoice archive | – | ✓ | ✓ |
| Export invoice set (closeout) | – | ✓ | ✓ |
| Reconciliation / reports | – | ✓ | ✓ |
| View across all projects | – | – | ✓ |

### 3.2 Menu visibility by role

- **Supervisor:** Purchases (create + upload receipt), Attendance, Workers (register),
  Rentals, Invoices — assigned projects only.
- **Engineer:** Purchases (approvals), Workers (verify), Rentals, Invoices (search),
  Reports — assigned projects.
- **Admin:** all of the above, plus cash release, cross-project view, and Reports.

**Enforcement:** hiding a menu item or button in the UI is convenience only. The real
permission check MUST live on each API endpoint — otherwise a hidden action can be
reached by calling the endpoint directly.

## 4. Data model (starting point)

Entity fields are indicative, not exhaustive. Use UUID primary keys.

- **Project** — id, name, location, status, created_at
- **User** — id, name, phone, role (supervisor | engineer | admin), project_ids
- **PurchaseRequest** — id, project_id, supervisor_id, items[], estimated_total,
  status (pending | approved | rejected | purchased | closed), approver_id,
  decided_at, created_at
- **CashAdvance** — id, request_id, amount_given, given_by, given_at
- **Invoice** (receipt) — id, request_id (nullable for rentals), project_id,
  category_id, vendor, actual_total, image_url, uploaded_by, uploaded_at
- **Category** — id, name (fixed list: tiling, piping, cement, paint, tools,
  rental, other…) — confirm the list with the client
- **Worker** — id, name, father_name, generated_code, photo_url, project_id,
  registered_by, created_at
- **AttendanceRecord** — id, worker_id, project_id, date, photo_url, gps_lat,
  gps_lng, task, recorded_by, signed_at
- **EquipmentRental** — id, project_id, equipment_type, vendor, cost, invoice_id,
  rented_by, rented_at

Key relationships: an Invoice is the evidence for a CashAdvance/PurchaseRequest; an
AttendanceRecord belongs to a Worker on a Project on a date; an EquipmentRental points
to its Invoice.

## 5. Features

### 5.1 Procurement & purchase requests
- Supervisor creates a request: items, quantities, estimated cost, project.
- Routes to the engineer, who approves or rejects (with reason).
- On approval, office releases a cash advance; the amount given is recorded.
- Supervisor buys, then **must** upload the receipt (photo) and type the actual total.
- System reconciles cash given vs. actual spent; flags large differences.
- A request cannot be marked `closed` without an attached receipt. (Confirm whether
  this is a hard block or a flag — see open questions.)

### 5.2 Daily attendance
- Supervisor records attendance daily (currently weekly — the main pain point).
- Each record captures worker photo + GPS + task, connected to the central database.
- Must work offline and sync later.

### 5.3 Worker registration (anti-ghost-worker)
- At hiring, supervisor registers the worker: name, father's name, photo, location.
- System generates a worker ID/code. Attendance references registered workers only,
  so "phantom" laborers can't be added invisibly across many projects.

### 5.4 Equipment & machinery rental
- Supervisor arranges rentals (bulldozer, excavator, generator, etc.).
- Rental invoice is uploadable and stored like a purchase receipt.

### 5.5 Invoice archive & retrieval (cross-cutting)
- Volume is high: ~10–15 invoices/project/day, ~100–200/day across all projects.
- No manual scanning: the phone photo captured at purchase **is** the archive entry.
- Each invoice inherits structured metadata (project, category, date, supervisor,
  amount, linked request) so filing is automatic.
- Retrieval is a filtered query: e.g. "all tiling invoices for Project X" returns
  instantly. Support export of a filtered set for project closeout.

## 6. Cross-cutting requirements

- **Attachments**: any record can carry an image; purchase receipts are mandatory.
  Tie every attachment to its record with uploader + timestamp for the audit trail.
- **Offline-first**: capture attendance/receipts offline, queue, sync when online.
- **Image handling**: compress phone photos on capture (~150–300 KB target).
- **i18n / RTL**: Dari UI; keep all strings externalized.
- **Auth & roles**: enforce role permissions on every endpoint, not just the UI.

## 7. Suggested build sequence (vertical slices)

Build one complete path end to end before widening. MVP first.

- **Slice 1 (MVP):** Purchase request → engineer approval → cash advance → mandatory
  receipt upload → reconcile → appears in a filterable invoice archive. This exercises
  the data model, approval flow, photo upload, and retrieval in one pass.
- **Slice 2:** Worker registration + daily attendance with photo + GPS + offline sync.
- **Slice 3:** Equipment rental with invoice attachment.
- **Slice 4:** Archive polish — advanced filters, export set, project-closeout reports.

For each slice: write the tests, then the schema, endpoints, and screens; verify with a
build/test run before moving on.

## 8. Open questions to confirm with the client

- Is a missing receipt a **hard block** on closing a purchase, or just a flag for the
  engineer to chase?
- Is there a **petty-cash limit** the supervisor can spend under without engineer
  approval (for urgent small buys), or does every purchase need approval?
- What is the exact **category list** for invoices/trades?
- Are there fixed **wage rates** per worker/day, or is that entered per record?

---

# Addendum — how this maps onto the Aria Herat ERP codebase

This section is repo-specific implementation guidance; the spec above is the source of
truth for behaviour. Written 2026-07-13.

## A. Reuse vs. build new

The ERP already has a lot the spec assumes. Do **not** duplicate these:

| Spec concept | Already in the system | Note |
|---|---|---|
| Project, project status/progress | `Project` (+ `syncProgress`) | System-driven progress stays as-is. |
| Categories, tenancy, soft-delete | `BelongsToCompany`, `CompanyScope`, `SoftDeletes` | Every new table uses these. |
| Roles & permissions | spatie/laravel-permission, `PermissionSeeder` | Add new `entity-action` perms here. |
| Attachment pattern | `PartyTransaction` (path/name/mime + download route) | Copy this exactly for receipts. |
| Multi-currency (AFN base) | `useCurrency`, locked rate-at-entry | AFN is the only currency the field uses. |
| Formal supplier POs | `PurchaseOrder` + `Supplier` + warehouse | **Different** from field cash-purchases — keep separate. |
| Office attendance/payroll | `AttendanceRecord`, `PayrollRun` | Employee-based. Field day-labor (Slice 2) is worker-based and distinct. |
| Activity audit | `ActivityLog::log(action, module, desc, ?projectId)` | Log every supervisor action with the project id. |

**New tables this module introduces:** `project_user` (assignment pivot),
`purchase_categories`, `purchase_requests`, `cash_advances`, `site_invoices`
(the field-invoice archive — named to avoid colliding with the client-billing
`Invoice` model). Workers / field-attendance / rentals arrive in Slices 2–3.

## B. The one real gap: per-project assignment

Roles today gate *what* a user can do, not *which project*. The spec requires
supervisor/engineer to be limited to assigned projects. Implement a `project_user`
pivot and a `Model::scopeForUser($user)` that:

- returns everything when the user is a Super Admin or `type === 'admin'`;
- otherwise restricts to the user's assigned `project_ids`.

Apply that scope to every supervisor-module query. Existing modules are unaffected.

## C. Confirmed decisions for the open questions (§8)

Confirmed by the client 2026-07-13; each remains a one-line change to flip later:

1. **Missing receipt = hard block.** A `purchase_request` cannot move to `closed`
   without at least one linked `site_invoice`. Matches "nothing exists without its
   evidence."
2. **Petty-cash limit = per-project field** (`projects.petty_cash_limit`), default `0`
   (every purchase needs approval). A request whose `estimated_total` is at/under a
   project's limit auto-approves and skips the engineer step.
3. **Category list (13, seeded + editable):** cement, rebar/steel, tiling,
   piping/plumbing, electrical, paint, wood/carpentry, tools, fuel, transport, rental,
   labor, other.
4. **Wage rates = per attendance record** (enter the day rate on the record). Arrives
   with Slice 2; a fixed per-worker rate can be added later.

## D. Verification workflow (unchanged house rules)

`php -l` new PHP → `migrate:fresh --seed` → tinker sanity on the flow → frontend
`pnpm build`. Commit as **Fazil**. Branch `claude/aria-herat-erp-analysis-5nnh55`.
Keep the model identifier out of commits/PRs/code.
