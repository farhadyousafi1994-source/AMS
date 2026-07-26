# Aria Herat ERP — Stepwise Hands-On Tutorial

A click-by-click training path. Follow it in order: first learn to **log in / out** with
every account, then do the **admin setup** (users, roles, branches), then walk through
**each role's daily work**. Every step says what to click and what you should see.

> Language: the app has an **EN / فارسی** switch at the top-right. Screenshots/labels below
> use the English labels. A Persian companion manual is in `docs/GUIDEBOOK_FA.md`.

---

## Part 0 — Before you start

- **Open the app** in Chrome/Edge (desktop) or the phone browser.
- **All demo accounts use the password:** `password`
- **Demo accounts:**

| # | Login (email) | Role | What they mainly do |
|---|---|---|---|
| 1 | `admin@ariaherat.af` | Super Admin | Everything + users, roles, branches, system |
| 2 | `president@ariaherat.af` | President (Owner) | Company-wide view, shareholders |
| 3 | `abubakr@ariaherat.af` | President (Shareholder) | Own income, deposit/withdraw |
| 4 | `omar@ariaherat.af` | President (Shareholder) | Own income, deposit/withdraw |
| 5 | `usman@ariaherat.af` | President (Shareholder) | Own income, deposit/withdraw |
| 6 | `ali@ariaherat.af` | President (Shareholder) | Own income, deposit/withdraw |
| 7 | `engineer@ariaherat.af` | Site Engineer | Projects, tasks, milestones, change orders |
| 8 | `kabul.engineer@ariaherat.af` | Site Engineer (Kabul) | Same, but Kabul branch |
| 9 | `fieldengineer@ariaherat.af` | Field Engineer | Field/site engineering |
| 10 | `supervisor@ariaherat.af` | Site Supervisor | Purchases, attendance, workers (mobile/offline) |
| 11 | `accountant@ariaherat.af` | Accountant | Treasury, receipts, expenses, accounts |
| 12 | `storekeeper@ariaherat.af` | Storekeeper | Suppliers, purchase orders, warehouse |
| 13 | `viewer@ariaherat.af` | Viewer | Read-only (auditor) |

> After real go-live, **change every password** (Part 2, step 4 shows how).

---

## Part 1 — Logging in and out (do this with every account)

### 1.1 How to log in
1. Open the app URL.
2. In **Email address**, type the account (e.g. `engineer@ariaherat.af`).
3. In **Password**, type `password`.
4. Click **Sign in**.
5. You land on the **Dashboard**. ✅

### 1.2 How to switch language
- Top-right, click the globe **EN ▾** → choose **فارسی** (the screen flips right-to-left) or **English**.

### 1.3 How to log out
1. Left menu, scroll to the bottom → click **Logout** (red).
   *(or top-right avatar → Logout).*
2. You return to the Sign-in screen. ✅

### 1.4 Feel the difference — log in as each account, one by one
For each account below, **log in**, look at the **left menu**, then **log out**. Notice how the
menu changes — each role only sees what it's allowed to.

- **`admin@ariaherat.af`** → sees **everything**, including **Administration** and **System**.
- **`president@ariaherat.af`** → sees company-wide data + **Finance → Shareholders**.
- **`engineer@ariaherat.af`** → sees **Projects, Site Management, Billing, Safety** — no Administration.
- **`supervisor@ariaherat.af`** → mostly **Site Management** (Purchase Requests, Field Attendance, Workers).
- **`accountant@ariaherat.af`** → **Finance & Accounting** in full.
- **`storekeeper@ariaherat.af`** → **Procurement & Assets** (Purchase Orders, Warehouse).
- **`viewer@ariaherat.af`** → can open pages but every **Add / Edit / Delete** button is hidden.

✅ **Checkpoint:** you can log in, switch language, and log out with any account, and you
understand that the menu depends on the role.

---

## Part 2 — Admin setup (log in as `admin@ariaherat.af`)

This is what an administrator sets up first: **branches → roles → users**.

### Step 1 — Create a Branch (office/region)
1. Left menu → **Administration → Branch**.
2. Click **Add Branch** (top-right).
3. Fill **Name** (e.g. `Herat Site Office`), address, phone.
4. Click **Save**. The branch appears in the list. ✅

### Step 2 — Create a Role and choose its permissions
*(Example: an "HR Clerk" role.)*
1. Left menu → **Administration → User Role**.
2. Click **Add Role**.
3. **Name:** `HR Clerk`.
4. Tick the permissions this role needs — for HR, tick the **Employee, Attendance, Payroll,
   Leave, Department, Designation** boxes (list/create/edit/show). Leave finance/admin unticked.
5. Click **Save**. ✅
   *(You can re-open any role later and tick/untick a whole module — e.g. give a role access
   to **Safety Incidents** by ticking `incident-*`.)*

### Step 3 — Create a User and assign role + branch
1. Left menu → **Administration → User**.
2. Click **Add User**.
3. Fill **Name**, **Email** (e.g. `hr@ariaherat.af`), **Password**.
4. **Role:** choose `HR Clerk` (from Step 2).
5. **Branch(es):** assign one or more (e.g. Herat). A user can even have **different roles in
   different branches**.
6. Click **Save**. ✅

### Step 4 — Verify (and how to change a password)
1. **Log out** (Part 1.3).
2. **Log in** as the new user (`hr@ariaherat.af`) → confirm they only see the HR menu.
3. To change any password later: **Administration → User → (edit the user) → set a new password → Save.**

### Optional admin setup
- **Currencies & exchange rate:** Finance & Accounting → **Currencies** / **Exchange Rates**
  (set today's AFN↔USD rate so money entries lock the correct rate).
- **Company logo/name:** used on exported PDFs and the app header.

✅ **Checkpoint:** you created a branch, a role, and a user, and confirmed the new user's access.

---

## Part 3 — Role-by-role daily work

### 3.1 Owner / President / Shareholder — `president@` (or `abubakr@`, `omar@`, `usman@`, `ali@`)
1. **Dashboard** → see the company map, KPIs (General Budget, Active Projects, Collected this month).
2. **Finance & Accounting → Shareholders** → four partner cards, each 25%.
3. To **withdraw** a share: click **Withdraw** on a partner → enter amount → **Save**.
   *(It posts to the General Budget and the partner's history. You can't withdraw more than available.)*
4. Scroll down to see **all shareholders' deposits/withdrawals** (transparency).
5. **Reports** (top-right on dashboard, or left menu) → open **Profit & Loss** → **Export PDF**.

### 3.2 Project Engineer — `engineer@ariaherat.af`
1. **Projects → Projects & Sites** → click **Add Project**.
2. Fill **Name, Budget, Currency, Client, Type, Branch, dates**.
3. **IMPORTANT — pick the location on the map:** in the *Map location* box, click the spot on
   the map (a red pin drops, coordinates show). **Without this, the project won't appear on the
   dashboard map.**
4. Click **Create & Continue** → add **Tasks**, **Milestones**, **Sites** in the wizard tabs.
5. Open the project later → update a **Task's progress**; the project's overall **% updates
   automatically** (progress is system-driven, never typed).
6. **Change Orders:** Projects → **Change Orders → Add** → on **Approve**, the contract value
   revises automatically.
7. Go back to **Dashboard** → your new project now shows as a **map pin** and a **skyline tower**. ✅

### 3.3 Site Supervisor — `supervisor@ariaherat.af` (best on a phone; works offline)
1. **Site Management → Purchase Requests → Add** → add items, **attach a photo of the bill** → Save.
2. **Site Management → Field Attendance** → pick the project/date → mark workers present/absent.
3. **Site Management → Workers** → add a worker (name, trade, wage, photo).
4. **Invoice Archive** → browse the archived bill photos.
5. **Offline test:** turn off Wi-Fi → create a Purchase Request (it queues) → turn Wi-Fi back on
   → open **System → Sync Center** → it shows **0 pending** after syncing. ✅

### 3.4 Quantity Surveyor / Billing — (any user with Billing access, e.g. `engineer@`)
1. **Billing → Bill of Quantities** → pick a project → **Add** items (item no, unit, quantity, rate).
2. **Billing → Payment Certificates → New Certificate** → enter the **cumulative quantity done**
   for each item → the system computes **this-period, retention, net payable** live.
3. **Save Draft → Certify → Print** the certificate. ✅

### 3.5 Accountant / Finance — `accountant@ariaherat.af`
1. **Finance → General Budget** → see money in/out and available.
2. **Finance → Receipts → Add** → record money received from a client.
3. **Finance → Home Expenses → Add** → choose a category and **Payment Method**. If you pick
   **Other**, a **"Please describe"** box appears — type what it is.
4. **Finance → Party Accounts** → see who owes whom (credit/debit balances).

### 3.6 Storekeeper / Procurement — `storekeeper@ariaherat.af`
1. **Procurement & Assets → Purchase Orders → Add Purchase Order** → add items, supplier, and
   **attach the bill** (attachment box).
2. **Procurement & Assets → Suppliers** → manage suppliers.
3. **Procurement & Assets → Warehouse** → stock items and movements.

### 3.7 Equipment / Fleet Manager — (user with Plant access)
1. **Procurement & Assets → Plant & Fuel → Add** → pick the machine, running hours, litres, price
   per litre, operator.
2. See KPIs update: **Total Fuel, Fuel Cost, Running Hours, Avg L/hr**. Attach the fuel receipt.

### 3.8 HSE / Safety Officer — (user with Safety access)
1. **Safety & Quality → Safety Incidents → Log Incident** → type, severity, location, injured count,
   immediate action → Save.
2. Later, **Close Incident** with the corrective action; reopen if needed.

### 3.9 HR — (the `HR Clerk` user you created in Part 2, or `admin@`)
1. **HR & Payroll → Employees → New Employee** (a full page) → fill details + photo → Save.
2. **HR & Payroll → Attendance** → daily staff attendance.
3. **HR & Payroll → Payroll Runs** → run payroll; **Leaves** → manage leave.

### 3.10 Viewer / Auditor — `viewer@ariaherat.af`
1. Open any page (Projects, Finance, Reports) — you can **see and export**, but there are **no
   Add/Edit/Delete buttons**. Ideal for inspectors/auditors.

---

## Part 4 — System tasks (admin)

- **System → Sync Center** → connection status, pending changes, and **conflict review**
  (Keep Mine / Keep Server) if two devices edited the same record offline.
- **System → Backup** → **Download Backup** (save the file safely); **Restore / Import** to
  bring data back (takes an automatic safety backup first).
- **System → Log** → who did what and when. **System → Trashes** → restore deleted records.
- **System → Theme** → colors/appearance.

---

## Appendix — Two things people ask about

1. **"My new project isn't on the dashboard/map."**
   - Make sure you **picked a location on the map** while creating it (Part 3.2, step 3).
   - The creator is automatically added to the project, so it's visible to you right away.
2. **PDF/Word export** always includes the **company logo, name, and date**, and **Persian text
   exports correctly**. Find **Export** on Reports and most list pages.

---

### Suggested tutorial-video order (record in this sequence)
1. Login / language / logout (Part 1) → 2. Admin creates branch, role, user (Part 2) →
3. Owner & shareholders (3.1) → 4. Engineer creates a project on the map (3.2) →
5. Supervisor offline purchase + sync (3.3) → 6. BOQ + Payment Certificate (3.4) →
7. Accountant receipts/expenses (3.5) → 8. Storekeeper PO (3.6) → 9. Plant & Fuel (3.7) →
10. Safety incident (3.8) → 11. HR employee + payroll (3.9) → 12. Viewer read-only (3.10) →
13. Backup & Sync Center (Part 4).
