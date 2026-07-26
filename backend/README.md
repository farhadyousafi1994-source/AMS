# Aria Herat ERP — Backend

Laravel 12 API for the Aria Herat Mohandes Zada ERP. Sanctum token auth,
`spatie/laravel-permission` RBAC, single-DB multi-tenancy via `company_id` +
a global `CompanyScope`.

## What's here (Platform scaffold — Phase 1)

Auth, Users, Roles & permissions, Companies (multi-company), Branches
(multi-branch), Activity Log, Notifications, Backup (stub), Trash counts,
Super Admin, and a Dashboard endpoint with honestly-zeroed placeholders for
the modules below.

Projects & Sites, Finance & Accounting, Procurement & Assets, and HR &
Payroll (Parts A–D of the client proposal) are **not built yet** — they're
Phase 2.

## Run

```bash
cd backend
composer install
cp .env.example .env   # defaults to sqlite
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve       # http://localhost:8000
```

Seeded admin: **admin@ariaherat.af / password**

## Tests

```bash
php artisan test
```
