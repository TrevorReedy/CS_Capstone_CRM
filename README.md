# TyphonCath CRM

An internal CRM for a medical-device distributor, replacing a spreadsheet-based
workflow. It covers customer accounts, the RFQ-to-quote sales pipeline,
marketing campaigns, and inventory with reservation tracking — behind a unified
dashboard and a role-based permission system.

**Stack:** PHP 8.2 (no framework — modular Controller/Service/Repository),
MySQL 8, Bootstrap 5, jQuery DataTables, Apache. Containerised for development,
deployed to cPanel shared hosting.

| | |
|---|---|
| Lines of application code | ~14,000 PHP |
| Automated tests | 217 PHPUnit tests (569 assertions) + 296 static wiring checks |
| CI | GitHub Actions — lint, security wiring, unit, integration |
| Deployment | Automated FTPS to Bluehost on merge to `main` |

---

## Quick start

The only prerequisite is **Docker Desktop**
([Windows](https://docs.docker.com/desktop/install/windows-install/) ·
[Mac](https://docs.docker.com/desktop/install/mac-install/) ·
[Linux](https://docs.docker.com/desktop/install/linux-install/)).
No PHP, no MySQL, no XAMPP.

```bash
git clone <repo-url>
cd typhoncath
docker compose up
```

Open **<http://localhost:8080>** and sign in:

| Field | Value |
|-------|-------|
| Email | `admin@typhoncath.test` |
| Password | `password` |

The first run takes a minute or two while Docker builds the image. The database
is created, seeded and indexed automatically.

> This demo credential is public — the bcrypt hash is in `seed.sql`. Fine
> locally; it must be changed before any deployment.

### Seeing the permission system work

The seed creates only a Super Admin, and that role deliberately bypasses every
permission check — so every page looks reachable. To see the matrix actually
restrict things, load one user per role:

```bash
docker compose exec -T db mysql -uroot -pdevonly_root typhon_cath_crm \
  < src/database/seed_dev_users.sql
```

| Email | Role | Reaches |
|-------|------|---------|
| `admin@typhoncath.test`  | Super Admin       | everything, including the permission matrix |
| `admin2@typhoncath.test` | Admin             | everything except the permission matrix |
| `sales@typhoncath.test`  | Sales User        | customers, RFQs, inventory (read-only) |
| `mktg@typhoncath.test`   | Marketing User    | campaigns, customers (read-only) |
| `inv@typhoncath.test`    | Inventory Manager | inventory, RFQs (read-only) |

All five share the demo password. Development only.

---

## What it does

| Module | Capability |
|---|---|
| **Customers** | Accounts and contacts, interaction history, tagging, searchable/filterable lists, PDF and CSV export |
| **RFQ pipeline** | Six-stage pipeline (New → In Review → Quoted → Negotiation → Won/Lost), quotes with validity windows and discount rules, inventory reservations attached to an RFQ, win-rate reporting |
| **Campaigns** | Email and SMS-simulation campaigns, audience segments built from tags or explicit account/contact selections, reusable audience presets, scheduling, simulated send with real recipient counts |
| **Inventory** | Products and stock levels, per-product low-stock thresholds, reserve/release/convert lifecycle driven by the RFQ pipeline, and an append-only movements ledger |
| **Dashboard** | 19 role-aware cards across the four domains; cards a user lacks permission for are never rendered or queried |
| **Admin** | User management and a live role/permission matrix |

Cross-cutting: session hardening with idle and absolute timeouts, CSRF
protection on every state-changing request, login throttling, security response
headers, and server-side DataTables for every list view.

---

## Documentation

Start here depending on what you're looking for:

| Document | Covers |
|---|---|
| [`src/docs/writeup/`](src/docs/writeup/) | **The fourteen design documents** — system context, module architecture, ERD, navigation map, role/permission matrix, RFQ state and sequence diagrams, inventory reservation flow, campaign audience flow, dashboard data flow, deployment architecture, CRUD matrix, non-functional qualities. Start at [`00_DIAGRAM_INDEX.md`](src/docs/writeup/00_DIAGRAM_INDEX.md). |
| [`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) | **What is simulated, unimplemented, or not production-grade.** Written to be read before a demo, not discovered during one. |
| [`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md) | Bluehost/cPanel deployment, the CI/CD pipeline, rollback, backups |
| [`src/docs/HANDOFF.md`](src/docs/HANDOFF.md) | Taking ownership: credentials to rotate, where everything lives, routine maintenance |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Branching, running the tests, adding a migration |
| [`src/docs/modules/`](src/docs/modules/) | One overview per module |
| [`src/docs/project/requirements.md`](src/docs/project/requirements.md) | The SRS this was built against |

---

## Architecture

```
src/
├── public/              Entry points — one PHP file per URL. No router.
│   ├── dashboard.php
│   ├── admin/
│   └── modules/{customer,rfq,campaign,inventory}/
├── app/
│   ├── Core/            Auth, Permissions, Csrf, Database, DataTable, PDF
│   ├── Middleware/       csrf, require_auth, require_role
│   ├── Shared/           layout, sidebar, header, flash
│   └── Modules/<Name>/
│       ├── <Name>Controller.php    request handling, no SQL
│       ├── <Name>Service.php       business rules and validation
│       ├── <Name>Repository.php    SQL, and nothing else
│       └── views/                  templates
├── config/              app, database, permissions
├── database/            schema.sql, seed.sql, indexes.sql, migrations/
├── docs/                design documents and operational guides
└── tests/               automated suites + manual test plans
```

There is no framework and no router: a URL maps to a file in `public/`, which
requires `app/Core/bootstrap.php`, checks a permission, and calls a controller.
Any request can be followed end to end by reading PHP.

Three deliberate structural decisions are documented where they are made:
`Database::connection()` is a static singleton (so repositories need no wiring),
`Super Admin` bypasses the permission matrix (so the matrix cannot lock everyone
out of itself), and permissions are cached in the session for 60 seconds (traded
against a query on every check).

---

## Testing

```bash
docker compose exec app composer test              # everything
docker compose exec app composer test:unit         # no database, milliseconds
docker compose exec app composer test:integration  # needs MySQL
docker compose exec app composer test:static       # CSRF + authorization wiring
docker compose exec app composer lint              # php -l over every file
```

| Suite | Size | Covers |
|---|---|---|
| `test:static` | 296 checks | Walks **every** entry point in `public/` and asserts each POST form and handler is CSRF-protected, and each route is permission-gated *before* it dispatches. Also verifies every permission string in code is one the seed actually grants — a typo is deny-all for real roles but invisible to a Super Admin. |
| `test:unit` | 108 tests | `Csrf`, `Validator`, `Permissions`, login throttling, the DataTables query builders (SQL injection surface), and service validation rules. |
| `test:integration` | 109 tests | Real MySQL 8: authentication and password rehashing, the inventory reservation lifecycle and its transaction boundaries, repositories, DataTables queries including FULLTEXT, all 19 dashboard cards, and the migration chain. |

The static harnesses cover something the unit tests structurally cannot: they
assert a property across the whole codebase, so a *newly added* page that
forgets CSRF or a permission gate fails the build even though nobody wrote a
test for it.

`src/tests/*.md` hold manual test plans for what automation does not reach —
browser behaviour and visual checks.

---

## CI/CD

One workflow, [`ci.yml`](.github/workflows/ci.yml), with five jobs:

| Job | Runs on | Does |
|---|---|---|
| `lint` | every push; PRs into `main` | `php -l` over every file; `composer validate --strict` |
| `static` | every push; PRs into `main` | CSRF and authorization wiring harnesses |
| `unit` | every push; PRs into `main` | PHPUnit unit suite, no database |
| `integration` | every push; PRs into `main` | PHPUnit integration suite against a `mysql:8.0` service |
| `deploy` | push to `main`, or manual | builds `vendor/`, uploads to Bluehost over FTPS |

The first four run in parallel. `deploy` declares `needs` on all four, so
nothing reaches the live site unless every one of them is green.

The integration job runs a `mysql:8.0` service container and rebuilds the schema
from `schema.sql` → `seed.sql` → `indexes.sql` on every run, so CI proves the
checked-in SQL actually builds a working database.

### Setting up automatic deployment

The pipeline replaces a manual FileZilla drag. It does one thing that dragging
files cannot: it runs `composer install --no-dev --optimize-autoloader` first.
`vendor/` is a gitignored build artifact and dompdf is a hard runtime
dependency, so a hand-copied checkout produces a site whose PDF export fatals.

**1 — Create an FTP account.** cPanel → **Files** → **FTP Accounts**. Note the
username, password, and server (usually `ftp.yourdomain.com`).

**2 — Find the deploy directory.** The document root points at the app's
`public/` folder, so `app/`, `config/` and `database/` sit *above* the web root
where no request can reach them:

```
/home/<cpaneluser>/
└── typhoncath/          <-- FTP_SERVER_DIR points HERE (note: not public_html)
    ├── app/  config/  database/  storage/  vendor/  .env
    └── public/          <-- the document root
```

Set this in cPanel → **Domains** → your domain → **Document Root** →
`typhoncath/public`.

**3 — Add four repository secrets.** GitHub → **Settings** → **Secrets and
variables** → **Actions** → **New repository secret**:

| Secret | Example | Notes |
|---|---|---|
| `FTP_HOST` | `ftp.yourdomain.com` | no `ftp://` prefix |
| `FTP_USERNAME` | `deploy@yourdomain.com` | the cPanel FTP account |
| `FTP_PASSWORD` | | that account's password |
| `FTP_SERVER_DIR` | `/typhoncath/` | the directory *containing* `public/`, trailing slash |

**4 — Dry run first.** Actions → **Deploy to Bluehost** → **Run workflow** →
tick **dry run**. The transfer is a mirror with `--delete`, meaning it removes
remote files not present locally; on a site that has only ever been uploaded by
hand, that difference can be large. The dry run lists every file it *would* send
and remove without touching anything. Read that log before continuing.

**5 — Deploy.** Re-run without dry run, or push to `main`.

**6 — Verify.** Log in, open the dashboard, search an account by name (proves
the FULLTEXT indexes loaded), and **export an RFQ as PDF** — that last one is
what proves the `vendor/` build actually arrived.

### What the deploy will never overwrite

The exclusion list in the `deploy` job is load-bearing, because the mirror deletes
remote files that are absent locally. Protected on the server:

- `.env` and `config/database.php` — server-owned configuration, never in git
- `public/uploads/**` — user data, not recoverable from git
- `storage/logs/**`, `storage/backups/**`, `database/backups/**` — runtime state

Also excluded, as they do not belong on a public host: `tests/`, `docs/`,
`composer.json`, `composer.lock`, `phpunit.xml`, and `database/seed_dev_users.sql`
(which creates five accounts sharing one published password).

### How the transfer works

No third-party deployment action is used. The job installs `lftp` from Ubuntu's
own package repositories and drives it directly, so the only things that handle
the FTP credentials are the GitHub runner and lftp itself. The password reaches
lftp through `--env-password` rather than a command-line argument, so it never
appears in the process list or a script body. Encryption is enforced rather than
negotiated: the transfer refuses to fall back to plaintext, encrypts the data
channel as well as the login, and requires a valid certificate.

### Gating deploys on tests

The `deploy` job declares `needs: [lint, static, unit, integration]`, so a
failing test stops the upload — that is why deployment is a job inside `ci.yml`
rather than a workflow of its own. Two separate workflows on the same trigger
start simultaneously, and the upload can finish before the tests do.

Two optional gates on top of that:

- **Branch protection** (**Settings** → **Branches** → `main`) requiring the
  four CI checks, so work reaches `main` only through a green pull request.
- **A human gate**: add a required reviewer under **Settings** →
  **Environments** → `production`; the deploy job already targets that
  environment.

To rehearse a deploy without uploading anything, use **Actions** → **CI** →
**Run workflow** with `dry_run` checked. `lftp` then prints what it *would*
transfer and delete. Off `main`, a manual run is only permitted as a dry run.

---

## Configuration

`docker compose up` needs no configuration. To override, create a `.env` next to
`docker-compose.yml`:

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_NAME` | `typhon_cath_crm` | Database name |
| `DB_USER` | `crm_user` | Application database user |
| `DB_PASS` | `devonly_change_me` | Application database password |
| `MYSQL_ROOT_PASSWORD` | `devonly_root` | MySQL root password |
| `DB_HOST_PORT` | `3306` | Host port for the database (see troubleshooting) |

Application settings live in `src/.env` — copy `src/.env.example` and edit.
Session timeouts and cookie behaviour are configured there.

---

## Daily use

| Task | Command |
|------|---------|
| Start | `docker compose up` |
| Start in background | `docker compose up -d` |
| Stop | `docker compose down` |
| Logs | `docker compose logs -f` |
| Install/update dependencies | `docker compose exec app composer install` |
| Reset the database | `docker compose down -v && docker compose up` |
| Database console (dev only) | `docker compose --profile tools up` → <http://localhost:8081> |

> **Reset warning**: `down -v` deletes the database volume. All data is wiped and
> re-seeded.

`vendor/` is gitignored, so run `composer install` after cloning and whenever
`composer.lock` changes.

---

## Database

| File | Role |
|------|------|
| `src/database/schema.sql` | Tables, keys, foreign keys, CHECK constraints |
| `src/database/seed.sql` | Demo data + the role/permission matrix |
| `src/database/indexes.sql` | **All** secondary and FULLTEXT indexes |
| `src/database/migrations/` | Ordered upgrades for databases that already exist |

Compose applies `schema` → `seed` → `indexes` in that order, once, on an empty
volume.

**`indexes.sql` is not optional.** RFQ, account and campaign search use MySQL
FULLTEXT; without it those searches fail outright with *"Can't find FULLTEXT
index matching the column list."*

For an existing database, apply the migrations in filename order from the first
one not yet applied. `021` is the current head. Read
`020_integrity_constraints.sql`'s header before running it — it repairs data
before adding constraints.

**A new database is built from `schema.sql`, never from `migrations/`.**
Migrations 001–005 contain no SQL: those tables have only ever been defined in
`schema.sql`, so the directory is a changelog of changes *since* the original
schema rather than a build script. Details in
[`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md).

### Backup and restore

```bash
php src/database/backup.php                              # timestamped dump -> database/backups/
php src/database/restore.php <dump.sql>                  # restore over the configured database
php src/database/restore.php <dump.sql> --into=scratch   # restore drill, non-destructive
```

Cron:

```
0 2 * * *  php /path/to/src/database/backup.php >> /path/to/backup.log 2>&1
```

Run the `--into=scratch` drill periodically — an untested backup is not a
backup. The drill needs a database user that can create databases; the
application user deliberately cannot.

Set `DB_SSL_MODE=DISABLED` if the client rejects a self-signed server
certificate.

---

## Troubleshooting

**Port 8080 already in use**
Change `"8080:80"` to `"8081:80"` in `docker-compose.yml`.

**Port 3306 already in use**
You have a local MySQL. Set `DB_HOST_PORT=3307` in `.env` — no need to edit
`docker-compose.yml`. The app is unaffected either way; that port is only for
connecting with an external DB client.

**Database connection error on first boot**
The app can start before MySQL finishes initialising. `docker compose restart app`.

**Search returns "Can't find FULLTEXT index"**
`indexes.sql` did not run. It executes only on an *empty* volume, so:
`docker compose down -v && docker compose up`.

**`vendor/autoload.php` not found**
Run `docker compose exec app composer install`.

**A page returns 403**
Working as intended — that account lacks the permission. Check Admin →
Permissions (Super Admin only).

---

## Connecting a database client

| Setting | Value |
|---------|-------|
| Host | `127.0.0.1` |
| Port | `3306` (or `DB_HOST_PORT`) |
| Database | `typhon_cath_crm` |
| Username | `crm_user` |
| Password | `devonly_change_me` |

---

## Known limitations

[`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) is an honest
account of what this build does not do — campaign sending is simulated, there is
no Excel import, login throttling is per-session, and the CSP still allows
inline scripts. Read it before demoing or deploying.

## License

MIT — see [`LICENSE`](LICENSE).
