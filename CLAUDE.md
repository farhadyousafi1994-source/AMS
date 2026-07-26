# Aria Herat ERP — project guide

Construction & road-building ERP for **Aria Herat Mohandes Zada** (Herat/Kabul,
Afghanistan). Quasar 2 + Vue 3 (`<script setup>`) frontend, Laravel 12 API. Bilingual
EN / Dari (RTL). Base currency **AFN** (multi-currency with locked rate-at-entry).

## Non-negotiable rules
- **Commits are authored as `Fazil <briskcodeio@gmail.com>`** — never as the model.
  Keep the model identifier out of commits, PRs, code, and comments.
- Develop and push on branch `claude/aria-herat-erp-analysis-5nnh55`.
- Capital and profit-share % are **independent** (never a formula). The company itself
  is a participant in every cap table. Underfunded projects are flagged, never blocked.
- Assets = returnable (total→allocated→available); Materials/stock = consumable — kept
  separate. Project progress is **system-driven** (avg of task progress).

## Architecture quick reference
- Multi-tenant: `company_id` + `CompanyScope` + `BelongsToCompany` + `App\Support\Tenant`.
- Auth: Sanctum; RBAC: spatie/laravel-permission (`PermissionSeeder`, `entity-action`).
- Money: `TreasuryTransaction` (General Budget), `useCurrency` composable, per-money-field
  locked daily rate. `ActivityLog::log(action, module, desc, ?projectId)` on every write.
- Global Vue components in `boot/globals.js`; custom i18n `$t('Key')` with
  `frontend/src/i18n/{en,fa}/index.js` (fa = Dari). Shared `stat-card`, `n-table`, `m-modal`.

## Verify before commit
`php -l` → `migrate:fresh --seed` → tinker sanity → frontend `pnpm build`
(`VITE_API_URL=http://127.0.0.1:8000`). Remove `frontend/dist` before committing.

## Feature specs
- **Supervisor & site management** (purchases, field attendance, workers, rentals,
  invoice archive; mobile-first, offline-first): see
  [`docs/supervisor-module-spec.md`](docs/supervisor-module-spec.md).
- Status & usage: [`docs/PROJECT_STATUS_AND_USAGE.md`](docs/PROJECT_STATUS_AND_USAGE.md).
