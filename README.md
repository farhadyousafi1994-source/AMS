# Aria Herat ERP

Enterprise Resource Planning platform for **Aria Herat Mohandes Zada**
(Engineering & Construction Company, Herat/Kabul, Afghanistan).

Built as a Quasar 2 + Vue 3 + Laravel 12 application, sharing the same
design system and platform architecture as the company's sibling ERP
(`fazil-erp`) — the shared **global header, table and button components**,
layout, RBAC, offline-first sync, multi-branch/multi-company support and
theme customization are cloned from that codebase and rebranded here.

## Stack

- **Backend:** Laravel 12 (PHP 8.3), Sanctum token auth, `spatie/laravel-permission`
- **Frontend:** Quasar 2 + Vue 3 (`<script setup>`), Pinia, axios, Dexie (offline IndexedDB cache)
- **Multi-tenancy:** single database, `company_id` + global `CompanyScope`

## What's included (Phase 1 — Platform Scaffold)

- Login page (branded, offline indicator, demo login)
- Navbar + Sidebar (search, branch switcher, language switcher, notifications, user menu)
- Construction-flavored Dashboard (honest empty/zero state — no fake numbers)
- Offline-First sync (IndexedDB cache + sync queue, `/offline` settings page)
- Activity Log (`/log`)
- Backup (`/backup`)
- Trash — soft-delete restore counts (`/trash`)
- Shared global header, table (`three_d` shadow design language) and progress button across every page
- Users & Roles (permission-matrix RBAC)
- Notifications
- Theme customization (10 construction-themed color presets, dark mode, font size, radius, sidebar style)
- Search (sidebar quick search + per-page advanced search)
- Multi-Branch (Kabul Head Office / Herat Site Office seeded)
- Multi-Company, English & Farsi (+ Pashto fallback)
- Logout

## What's NOT built yet (Phase 2 — per the Nova Technologies proposal)

**Projects & Sites**, **Finance & Accounting**, **Procurement & Assets**, and
**HR & Payroll** (Parts A–D of the client proposal) are previewed in the
sidebar as "coming soon" placeholders but have no backend or business logic
yet. This scaffold is the platform they'll be built on top of.

## Branding note

`frontend/src/assets/brand/logo-mark.svg` is a **placeholder** brand mark
(skyscraper + gold gradient, evoking the client's real logo). Swap that one
file with the client's actual logo once supplied — nothing else needs to change.

## Run locally

### Backend
```bash
cd backend
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve       # http://localhost:8000
```
Seeded admin: **admin@ariaherat.af / password**

### Frontend
```bash
cd frontend
pnpm install            # or npm install / yarn
pnpm dev                # http://localhost:9000
```
Set `VITE_API_URL` in `frontend/.env` if the API isn't on `:8000`.

## Layout

```
backend/    Laravel API
frontend/   Quasar SPA
```
