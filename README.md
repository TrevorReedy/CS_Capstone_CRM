# Typhon Cath CRM

A self-hosted, browser-based CRM for Typhon Cath, built in PHP 8.2 and MySQL 8.
It replaces a set of Excel spreadsheets with one internal tool covering customer
records, the sales pipeline (RFQs and quotes), digital campaigns, and inventory,
behind a role-based login shared by the whole team.

Built as a university capstone project: each functional area was owned by a
different student on one codebase, one database, and a common `Core` layer for
auth, routing and permissions.

---

## Start here

If you are taking this system over, read these in order. This README explains
what the system is and how it is built and deployed; the documents below are the
operational detail.

| Document | What it covers |
|---|---|
| [`src/docs/HANDOFF.md`](src/docs/HANDOFF.md) | **Read first.** Credentials to rotate before go-live, where everything lives, routine maintenance |
| [`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) | What is simulated, unimplemented, or not production-grade. Read before any demo |
| [`src/docs/setup.md`](src/docs/setup.md) | Local development setup (Docker) |
| [`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md) | Bluehost / cPanel setup, deploys, rollback, backups |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Branching, running tests, adding a migration |
| [`src/docs/writeup/`](src/docs/writeup/) | Fourteen numbered design documents (ERD, sequence diagrams, role matrix, deployment architecture) |
| [`src/docs/project/requirements.md`](src/docs/project/requirements.md) | The SRS — the gold standard for scope |

---

## What is built

| Area | State |
|---|---|
| Login, sessions, role-based access control | Complete — 5 roles, permission matrix editable in Admin |
| Customer accounts, contacts, interaction history | Complete |
| RFQ pipeline, quotes, deal conversion, win-rate report | Complete |
| Inventory catalogue, stock, RFQ reservations, movement ledger | Complete |
| Campaigns, audience selection, send simulation | Complete — **sending is simulated; no email or SMS leaves the system** |
| Unified dashboard | Complete — 19 permission-aware cards |
| List views: search, sort, column filters, paging, CSV/Excel/PDF export | Complete on all five list pages |
| PDF reports for RFQ, account and campaign detail | Complete (server-side, dompdf) |
| Admin user management and permission matrix | Complete |
| Excel import of the client's existing spreadsheets | **Not built** |
| Consolidated Reports page | **Not built** — reporting lives in dashboard cards and per-module exports |
| Password reset / MFA / user deactivation | **Not built** |

[`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) is the full,
honest list, including data-model and security caveats.

---

## Running it locally

Docker is the only prerequisite — no PHP, no MySQL, no XAMPP.

```bash
git clone <repo-url>
cd typhoncath
docker compose up
```

The app comes up on <http://localhost:8080>. The database is created, seeded and
indexed automatically on first start. Log in with `admin@typhoncath.test` /
`password` (a public demo credential — change it before any deployment).

```bash
docker compose exec app composer install   # once after cloning; vendor/ is gitignored
docker compose exec app composer test      # static harnesses + full PHPUnit suite
docker compose --profile tools up          # adds Adminer, a DB console, on :8081
```

Full detail, role-specific demo users, and troubleshooting are in
[`src/docs/setup.md`](src/docs/setup.md). Docker is a **development convenience
only** — production is a plain Apache/PHP/MySQL host with no containers.

---

## Continuous integration and deployment

Everything below lives in one file: [`.github/workflows/ci.yml`](.github/workflows/ci.yml).
There is no second workflow and no third-party deployment action.

### The pipeline

```text
push to any branch, or a pull request into main
   │
   ├─ lint          php -l over every non-vendor PHP file
   │                composer validate --strict  (composer.json vs composer.lock)
   │
   ├─ static        tests/csrf_coverage.php   — 198 checks
   │                tests/authz_coverage.php  — 111 checks
   │
   ├─ unit          PHPUnit "unit" suite — no database, runs in milliseconds
   │
   └─ integration   PHPUnit "integration" suite against a real MySQL 8 service
                    container, rebuilt from schema.sql + seed.sql + indexes.sql
        │
        └── all four green ──►  deploy   ← only on a push to main
                                  │
                                  ├─ composer install --no-dev --optimize-autoloader
                                  ├─ sanity check: vendor/autoload.php exists,
                                  │  dompdf present, public/index.php parses
                                  └─ lftp mirror --reverse --delete over FTPS
                                       → Bluehost (cPanel shared hosting)
```

The four test jobs run in parallel and use no secrets, so they work on forks and
for contributors without repository access.

### The gate

`deploy` declares `needs: [lint, static, unit, integration]`. Nothing reaches the
live site until all four pass. This is why deploy lives in `ci.yml` rather than
its own workflow — as two workflows on the same trigger they started
simultaneously, and a deploy could finish before the tests it was supposed to
wait for had failed.

Two further gates are available in repository settings without touching the
workflow:

- **Branch protection** (Settings → Branches → `main`) — require the four checks
  before a pull request can merge, plus "do not allow bypassing" so a direct
  push to `main` cannot skip them.
- **A required reviewer** (Settings → Environments → `production`) — the deploy
  job already targets that environment, so adding a reviewer makes every deploy
  wait for a human.

### When it deploys

| Trigger | Result |
|---|---|
| Push to `main` | Full CI, then automatic deploy |
| Push to any other branch | Full CI, no deploy |
| Pull request into `main` | Full CI, no deploy |
| Actions → Run workflow, from `main` | Full CI, then deploy |
| Actions → Run workflow, from another branch | Allowed **only** as a dry run |

The last row is deliberate: "let me test the pipeline from my branch" must not
become "let me publish my branch". A dry run prints every file it would upload
and every file it would delete, and transfers nothing.

**The very first deploy against an existing site should be a dry run.**
`mirror --delete` removes anything on the server that is absent locally, and on a
site that has only ever been hand-uploaded via FileZilla that difference can be
large.

### Why the pipeline exists at all

It runs `composer install --no-dev --optimize-autoloader` before uploading.
`vendor/` is a gitignored build artifact and dompdf is a hard runtime dependency,
so **copying the repository to a server by hand produces a site whose PDF exports
fatal on the first request.** That is the failure the pipeline was built to
remove. (`--no-dev` also keeps PHPUnit and its ~30 transitive packages off the
live host.)

### What it will never overwrite

`mirror --delete` is destructive by design, so the `EXCLUDES` list in the deploy
job is load-bearing. Protected on every deploy:

- `.env` and `config/database.php` — server-owned credentials
- `public/uploads/**` — user data, not recoverable from git
- `storage/logs/**`, `storage/backups/**`, `database/backups/**` — runtime state

Also excluded, as they have no business on a public host: `tests/`, `docs/`,
`composer.json`, `composer.lock`, `phpunit.xml`, and
`database/seed_dev_users.sql` (which creates five accounts sharing one published
password).

### Credentials it needs

Four repository secrets, at Settings → Secrets and variables → Actions:

| Secret | Value |
|---|---|
| `FTP_HOST` | e.g. `ftp.yourdomain.com` |
| `FTP_USERNAME` | the cPanel FTP account |
| `FTP_PASSWORD` | that account's password |
| `FTP_SERVER_DIR` | the directory **containing** `public/`, with a trailing slash — e.g. `/typhoncath/` |

`FTP_SERVER_DIR` is **not** `public_html`. The document root points at the app's
`public/` directory so that `app/`, `config/`, `database/` and `.env` sit one
level above the web root, where no request can reach them.

The transfer is `lftp` (a standard Ubuntu package) driven directly, so the only
things that ever handle the FTP credentials are the GitHub runner and lftp
itself. The password is read from the environment via `--env-password`, so it is
never a command-line argument visible in the process list. Encryption is enforced
rather than negotiated: `ftp:ssl-force` refuses to fall back to plaintext,
`ftp:ssl-protect-data` encrypts the data channel and not just the login, and
`ssl:verify-certificate` fails the deploy on a bad certificate instead of quietly
downgrading.

### Rolling back

There is no server-side version history, so a rollback is a redeploy of the
previous commit — `git revert` and push, or run the workflow from an earlier tag.
**A rollback does not undo database changes**; if the bad deploy applied a
migration, restore from a backup. See
[`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md).

---

## Testing

```bash
docker compose exec app composer test              # everything
docker compose exec app composer test:unit         # no database, milliseconds
docker compose exec app composer test:integration  # needs MySQL
docker compose exec app composer test:static       # CSRF + authorization wiring
docker compose exec app composer lint              # php -l over everything
```

| Layer | What it is |
|---|---|
| **PHPUnit** | 217 tests, 569 assertions. A unit suite needing nothing, and an integration suite against a real MySQL 8 rebuilt from `schema.sql` + `seed.sql` + `indexes.sql`. One test is deliberately incomplete and reports as such — the schema-versus-migrations drift check (see Known Limitations) |
| **`csrf_coverage.php`** | 198 static checks. Reads source rather than running the app: every `<form method="POST">` renders a token, every POST handler enforces one |
| **`authz_coverage.php`** | 111 static checks. Every entry point checks a permission *before* it dispatches, and every permission string it names is one the seed actually grants |
| **`src/tests/*_tests.md`** | Manual test plans per module, for visual and browser behaviour |

The two static harnesses cover what PHPUnit cannot: they walk *every* entry point
in `public/`, including ones nobody wrote a test for. A new page that forgets
`Csrf::field()` or a permission gate fails CI rather than shipping quietly.

There are no browser or end-to-end tests, and no load testing.

---

## How the code is organized

```text
src/
├── app/
│   ├── Core/             # auth, database, permissions, CSRF, pagination, DataTable, PDF
│   ├── Middleware/       # auth and CSRF guards, included per entry point
│   ├── Modules/
│   │   ├── Customer/     # accounts, contacts, interaction history
│   │   ├── RFQ/          # pipeline, quotes, deal conversion
│   │   ├── Campaign/     # campaigns, audience segmentation, send simulation
│   │   ├── Inventory/    # catalogue, stock, reservations, movement ledger
│   │   ├── Dashboard/    # cross-module metric cards
│   │   └── Admin/        # users, roles, permission matrix
│   └── Shared/           # layout partials (header, sidebar, footer, 403)
├── config/               # database.php (server-only) and app config
├── database/
│   ├── schema.sql        # full baseline schema — how a fresh install is built
│   ├── seed.sql          # role/permission matrix and demo data
│   ├── indexes.sql       # secondary and FULLTEXT indexes — NOT optional
│   └── migrations/       # numbered incremental changes since the baseline
├── docs/                 # all project documentation
├── public/               # THE DOCUMENT ROOT — the only web-accessible directory
├── storage/              # logs and backups
└── tests/                # PHPUnit suites, static harnesses, manual test plans
```

`public/` is the **only** directory a browser may reach. `app/`, `config/`,
`database/` and `storage/` must sit outside the web root.

### Routing: a front controller per module, not a central router

`app/Core/Router.php` and the `*_routes.php` file in each module exist but **are
not wired up anywhere** — they are placeholders from an earlier design. What
actually runs the site is simpler: a URL is a file. Each module has one entry
point under `public/modules/<module>/` that reads `$_GET['page']` and the HTTP
method and branches by hand.

```php
// public/modules/inventory/products.php
$controller = new InventoryController();
$page       = $_GET['page'] ?? 'list';

if ($page === 'detail') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        denyUnlessAllowed($isEdit ? 'inventory.edit' : 'inventory.create');
        $controller->save();
        exit;
    }
    denyUnlessAllowed('inventory.view');
    $controller->show();
} elseif ($page === 'stock')  { /* ... */ }
  elseif ($page === 'ledger') { /* ... */ }
  else                        { /* default: product list */ }
```

So `/modules/inventory/products.php?page=detail&id=7` means "run the `detail`
branch of the Inventory entry point". If you can read PHP you can follow any
request end to end.

### Request lifecycle, end to end

Saving an edited product walks every layer:

```text
Browser POSTs to /modules/inventory/products.php?page=detail
      ↓
products.php runs first:
  bootstrap.php          → error logging, security headers, hardened session
  Auth::requireLogin()   → no session? redirect to /login.php
  Middleware/csrf.php    → POST without a valid token gets a 403
  denyUnlessAllowed('inventory.edit')   → wrong role gets the shared 403 page
      ↓
InventoryController::save()        → reads and casts $_POST into typed values
      ↓
InventoryService::updateProduct()  → BUSINESS RULES: required fields, price >= 0,
                                      duplicate-SKU check, human-readable change note
      ↓
InventoryRepository::updateProduct()  → one UPDATE, prepared PDO statement
InventoryRepository::logMovement()    → INSERT into inventory_movements (audit trail)
      ↓
Controller sets $_SESSION['flash'], sends a Location: header, exit()s
      ↓
Browser GETs the redirect target → checks run again → Controller::show()
      ↓
sidebar.php prints the flash banner once and unsets it
```

Every state-changing action ends in a redirect plus a one-time flash message
rather than rendering a POST result directly. That is what makes "hit refresh"
safe everywhere in the app.

### The three layers

- **Controller** — HTTP only. Reads `$_GET`/`$_POST`, casts input, calls the
  Service, then includes a view or sets a flash and redirects. Never writes SQL,
  never holds a business rule.
- **Service** — business rules and orchestration, with no knowledge of HTTP.
  `InventoryService::createProduct()` validates, checks for a duplicate SKU,
  inserts, *then* logs the creation as a movement — so "create product" is always
  paired with an audit-trail entry.
- **Repository** — the only layer that touches the database, always through
  `App\Core\Database::connection()` (a lazily created singleton `PDO`) and always
  with prepared statements. This is what keeps user input out of SQL strings.

> **One inconsistency worth knowing about.** RFQ, Campaign, Inventory and Admin
> follow this split. **Customer does not**: `CustomerService.php` and
> `CustomerController.php` are stubs, and `public/modules/customer/account_detail.php`
> is a ~600-line entry point that dispatches on a hidden POST marker and holds its
> own logic inline. It is fully CSRF-protected and permission-gated — each write
> marker maps to its own permission, deny-by-default — but it is the one place in
> the codebase that does not look like the rest.

### The view layer

Nothing renders a full HTML document by itself. `layout_open()` /
`layout_close()` (`app/Shared/layout.php`) wrap every page in the shared header,
sidebar and footer; `layout_deny()` renders the shared 403 inside that same
chrome, so a blocked page still looks like part of the app. Views are plain PHP
files of HTML with inline `<?= ... ?>`, and **all output is escaped with
`htmlspecialchars()` at the point of printing** — that is the app's XSS defence.
There is no template engine.

### Authentication and sessions

`Auth::attempt()` looks the user up by email, `password_verify()`s against the
stored bcrypt hash, joins `role_permissions` to collect every permission string
for their role, calls `session_regenerate_id(true)` against session fixation, and
stores one array in the session:

```php
$_SESSION['user'] = [
    'id' => 7, 'name' => 'Jane', 'role' => 'Sales User',
    'permissions' => ['customers.view', 'rfqs.create', ...],
];
```

`bootstrap.php` hardens the session before it starts — `HttpOnly`, `SameSite`,
`Secure` when configured, strict mode, plus idle and absolute timeouts — and
emits the security response headers (CSP, `X-Frame-Options`, `nosniff`,
`Referrer-Policy`) from PHP rather than `.htaccess`, since shared cPanel hosting
may ignore `.htaccess`.

### Authorization

`Permissions::can('inventory.edit')` reads the cached permission list, unless the
role is `Super Admin`, which short-circuits to `true` — the deliberate
break-glass role, so the permission matrix cannot lock everyone out of itself.
Every other role, `Admin` included, is fully governed by the matrix.

The cached copy is **refreshed from the database after 60 seconds**
(`Permissions::REFRESH_AFTER_SECONDS`), so a permission change reaches an active
session within a minute without adding a query to every page load.

Two enforcement points, used together: `Middleware/require_auth.php` asks "are
you logged in at all", and every entry point calls `denyUnlessAllowed()` /
`Permissions::require()` before each branch of its `page` switch. The sidebar
only advertises links the current user can actually open.

### CSRF protection

`Csrf::token()` creates one 64-character random token per session and reuses it
for every form. `Csrf::field()` renders it as a hidden `_csrf` input,
`Csrf::metaTag()` exposes it to AJAX, and `Middleware/csrf.php` — included right
after `bootstrap.php`, before any state-changing logic — no-ops on GET/HEAD/
OPTIONS and otherwise compares in constant time (`hash_equals`), returning 403 on
a mismatch. `tests/csrf_coverage.php` makes this impossible to silently regress.

### List views

The five main lists (RFQ pipeline, Customer accounts, Campaigns, Inventory
products, Admin users) are DataTables with **server-side processing**: the page
renders only the table shell, and rows come from a companion `*_data.php`
endpoint through the shared `App\Core\DataTable\ServerTable` helper. That gives
searching, per-column filtering, sorting, page sizes of 10/25/50/100/All, and
CSV/Excel/PDF export from one place. `App\Core\Paginator` is still used for the
two server-rendered reports that are not DataTables: the RFQ win-rate drill-down
and the inventory ledger.

### The Dashboard

The one part of the app that intentionally reaches across module boundaries.
`DashboardService` constructs `RFQRepository`, `CampaignRepository` and its own
`DashboardRepository` and exposes one method per metric — it never writes SQL,
it only composes calls into each module's existing repository.

The grid is built from 19 small polymorphic **Card** classes
(`app/Modules/Dashboard/Cards/*.php`). A card declares a `title()`, an optional
`permission()` (so a Marketing user never sees an inventory card), and a `body()`
from one of two shared renderers — `stat()` for a single number, `preview()` for
a truncated top-N list with a deep link. `DashboardController` registers them all
and renders only those whose `visible()` returns true.

### The database layer

`Database::connection()` lazily creates one `PDO` per request
(`ERRMODE_EXCEPTION`, `FETCH_ASSOC`) from `config/database.php`, which reads
`getenv('DB_*')` — the same variables `docker-compose.yml` injects locally and
that a real host sets as environment variables. Every repository shares it.

`database/schema.sql` is the baseline a fresh install is built from;
`database/migrations/NNN_*.sql` are the incremental changes since. **The
migrations cannot rebuild the schema** — `001`–`005` contain no SQL — so never
build a production database from them. See
[`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md).

---

## Security notes

- Only `public/` is web-accessible. `app/`, `config/`, `database/`, `storage/`
  and `docs/` must never be reachable from a browser.
- Passwords are bcrypt-hashed; plaintext is never stored or logged.
- Every protected page requires an active session and a permission check, both
  re-evaluated on every request.
- Every POST form renders and validates a CSRF token — enforced by CI.
- Every database access goes through prepared statements in a Repository; no
  string-concatenated SQL.
- Exception text is never rendered into a response when `APP_DEBUG=false`; errors
  go to `storage/logs/application.log`, outside the document root.
- Sessions carry `HttpOnly` / `SameSite` / `Secure` flags with idle and absolute
  timeouts, and are regenerated on login.

Caveats that are still true — per-session (not per-IP) login throttling, a CSP
that must still allow `unsafe-inline`, and credentials recoverable from the git
history that **must be rotated on the server** — are documented in
[`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) and
[`src/docs/HANDOFF.md`](src/docs/HANDOFF.md).

---

## Module ownership

| Student | Module | Folder |
|---|---|---|
| Max | Customer Management | `app/Modules/Customer/` |
| Trevor | RFQ / Pipeline Management | `app/Modules/RFQ/` |
| Jonah | Digital Campaign Management | `app/Modules/Campaign/` |
| Casey | Inventory Management | `app/Modules/Inventory/` |
| All | Dashboard, Admin, Integration, Auth, CI/CD | `app/Modules/Dashboard/`, `app/Modules/Admin/`, `app/Core/` |

Jonah left the group partway through; the Campaign module was completed by the
remaining members.

---

## License

MIT — see [`LICENSE`](LICENSE).
