# PostgreSQL (central) + offline sync — setup & operations

Central database = **PostgreSQL** (`aria_herat_construction_prod`), the single source
of truth holding 100% of records. Each field device keeps an **IndexedDB** replica of
its working set and syncs through the Laravel API. This is the confirmed architecture:
**Dexie/IndexedDB edge · hybrid UUID sync-key (integer PKs kept) · fresh Postgres start.**

> The device store is IndexedDB, not a SQLite file — this is a web PWA and browsers
> cannot open SQLite. If a native Capacitor app is built later, `@capacitor-community/sqlite`
> becomes the edge store with the **same** sync API below.

---

## 1. Provision PostgreSQL

**Local dev (Ubuntu/WSL):**
```bash
sudo apt update && sudo apt install -y postgresql-16
sudo service postgresql start
```
**macOS:** `brew install postgresql@16 && brew services start postgresql@16`

**Production:** prefer a managed instance (AWS RDS / DigitalOcean / Azure Postgres, v16),
daily snapshots on, TLS required. On a self-managed server, put the data directory on a
durable volume and restrict `pg_hba.conf` to the app host only.

## 2. Database, role, permissions
```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE aria_herat_admin LOGIN PASSWORD 'REPLACE_WITH_STRONG_SECRET';
CREATE DATABASE aria_herat_construction_prod OWNER aria_herat_admin;
GRANT ALL PRIVILEGES ON DATABASE aria_herat_construction_prod TO aria_herat_admin;
\c aria_herat_construction_prod
GRANT ALL ON SCHEMA public TO aria_herat_admin;
SQL
```

## 3. Connection config + pooling

`backend/.env`:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aria_herat_construction_prod
DB_USERNAME=aria_herat_admin
DB_PASSWORD=REPLACE_WITH_STRONG_SECRET
DB_CHARSET=utf8
DB_SCHEMA=public
DB_SSLMODE=prefer

SESSION_DRIVER=database
SESSION_CONNECTION=pgsql
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=pgsql
CACHE_STORE=database
DB_CACHE_CONNECTION=pgsql
```
Connection string form (tools/CI): `postgresql://aria_herat_admin:SECRET@127.0.0.1:5432/aria_herat_construction_prod`

**Pooling (30+ users):** run **PgBouncer** in `transaction` mode in front of Postgres and
point `DB_PORT` at it (6432). Keep sessions/cache/queue on Postgres (or move to Redis later);
never route write-hot cache/sessions onto SQLite.

`config/database.php` already ships a `pgsql` connection and `'default' => env('DB_CONNECTION', 'sqlite')`,
so setting `DB_CONNECTION=pgsql` is all that's needed to switch the whole app.

## 4. Wire in + migrate (fresh start)
```bash
cd backend
composer install
php artisan key:generate      # if a fresh app key is needed
php artisan migrate:fresh --seed
```
`migrate:fresh --seed` builds all 82 tables + the sync columns (`uuid`, `revision`) on
Postgres and seeds demo/company data. The sync migration is Postgres-native (`uuid`,
`bigint`, unique index).

## 5. Seed + verify
```bash
php artisan tinker --execute="echo DB::connection()->getDriverName().' / '.DB::connection()->getDatabaseName();"
# expect: pgsql / aria_herat_construction_prod
php artisan tinker --execute="echo App\Models\Project::count().' projects, uuid on first: '.optional(App\Models\Project::first())->uuid;"
```

## 6. Automatic backups + restore

**In-app (driver-aware):** System → Backup.
- **Download** → `pg_dump --format=custom` file (`backup-*.dump`).
- **Restore/Import** → validates the `PGDMP` header, takes a safety backup, then
  `pg_restore --clean --if-exists --single-transaction`.
- Needs `pg_dump`/`pg_restore` on the app server's `PATH` (`sudo apt install postgresql-client-16`).

**Scheduled daily** (already in `routes/console.php`, keeps newest 14): ensure the scheduler runs —
```bash
* * * * * cd /var/www/aria-herat/backend && php artisan schedule:run >> /dev/null 2>&1
```

**Manual CLI:**
```bash
pg_dump -Fc -U aria_herat_admin aria_herat_construction_prod > backup.dump      # export
pg_restore -c --if-exists -U aria_herat_admin -d aria_herat_construction_prod backup.dump   # restore
```

## 7. Test offline → online sync end-to-end

**In the browser (matches the automated test that verified this):**
1. Log in, then simulate an offline write and sync from the console:
   ```js
   const u = window.__ariaSync.db.newUuid()
   await window.__ariaSync.db.enqueueSync('safety_incidents','create',
     { type:'incident', severity:'medium', title:'OFFLINE test', incident_date:'2026-07-18' }, u)
   await window.__ariaSync.db.pendingCount()      // 1  (queued in the outbox)
   await window.__ariaSync.service.sync()         // push + pull
   await window.__ariaSync.db.pendingCount()      // 0  (pushed & cleared)
   ```
2. Confirm the central DB has it:
   ```bash
   psql -U aria_herat_admin -d aria_herat_construction_prod \
     -c "select uuid,id,title from safety_incidents where title='OFFLINE test';"
   ```
   The row exists with the **same uuid** and a server-assigned integer `id`.

**Real device flow:** turn off Wi-Fi/data, create records in the app (they queue in the
outbox), turn connectivity back on — the 30-second auto-sync (or the manual sync button in
the sync-status bar) pushes them up and pulls the latest down.

---

## How the sync works (reference)

- **Identity:** every sync table has a client-generated `uuid` (unique) + a `revision`
  counter. Integer PKs/FKs are unchanged. The server upserts by `uuid`, so records made
  offline on different devices never collide.
- **Outbox:** offline writes queue in IndexedDB (`syncQueue`) as `{table, op, uuid, base_revision, payload}`.
- **Push:** `POST /api/sync/push` applies the batch; each result is `applied | conflict | error`.
- **Pull:** `POST /api/sync/pull {tables, since}` returns the delta (including soft-deleted
  rows) since the last sync; the device upserts live rows and removes tombstoned ones.
- **Conflicts:** if a device pushes against a stale `base_revision`, the server returns
  `conflict` and the change lands in the local `conflicts` store for review — never a silent overwrite.
- **Deletes:** financial/audit rows soft-delete; the `deleted_at` flag propagates as a tombstone.
- **Audit:** every synced write is recorded in `ActivityLog` (user + timestamp).
- **Registry:** `config/sync.php` (server) and `src/services/syncTables.js` (client) list the
  27 sync-eligible tables — add a table in both places to extend coverage.
