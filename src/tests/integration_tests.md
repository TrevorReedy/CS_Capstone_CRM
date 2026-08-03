# Integration Tests

Cross-module behaviour, deployment, security and the automated harnesses.
Module-specific cases live in the other four plans in this directory.

**Environment:** `docker compose down -v && docker compose up` (empty volume),
then load `database/seed_dev_users.sql`.
**Last executed:** 2026-08-03. Rows marked PASS were actually run.

## Automated — run these first

Static, no server or database required, non-zero exit on failure.

| # | Test | Command | Expected | Status |
|---|------|---------|----------|:---:|
| X1 | PHP syntax | `php -l` over all first-party files | No errors | PASS |
| X2 | CSRF coverage | `php tests/csrf_coverage.php` | 176 passed, 0 failed | PASS |
| X3 | Authorization coverage | `php tests/authz_coverage.php` | 111 passed, 0 failed | PASS |

`authz_coverage.php` was itself mutation-tested — removing a gate, moving a gate
below its dispatch, and misspelling a permission each make it fail. A harness
that cannot fail proves nothing.

## Fresh install

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| X4 | Empty-volume boot | `docker compose down -v && docker compose up` | App reachable at :8080 | PASS |
| X5 | Schema applied | Count tables | 15 | PASS |
| X6 | Indexes applied | Count distinct indexes | 52, including 3 FULLTEXT | PASS |
| X7 | Constraints applied | Count CHECK constraints | 9 | PASS |
| X8 | Seed applied | Count accounts / RFQs / permissions | 12 / 37 / 121 | PASS |
| X9 | **Search works with no manual SQL** | Search `Catheter` in the RFQ list | 20 results, no FULLTEXT error | PASS |
| X10 | Login | `admin@typhoncath.test` / `password` | 302 to dashboard | PASS |

> X6/X9 are the fresh-install regression: Compose used to mount only `schema.sql`
> and `seed.sql`, so a new database had **zero** secondary indexes and search
> failed outright with "Can't find FULLTEXT index matching the column list."

## Upgrade path

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| X11 | Migration on an old database | Build from the pre-change `schema.sql`, inject violating data (negative price, negative stock, discount > amount, duplicate reservations, orphan role), run `020_integrity_constraints.sql` | Runs clean: 9 constraints added, bad data repaired, duplicate reservations merged (7+5 → 12), orphan role owner nulled | PASS |

## Cross-module linking

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| X12 | RFQ → Customer | Open an RFQ detail | Account and contact shown and linked | PASS |
| X13 | RFQ → Inventory | Reserve a product against an RFQ | Reservation visible from both modules; stock moves | PASS |
| X14 | Campaign → Customer | Add accounts/contacts to an audience | Rows in `campaign_audience` | PASS |
| X15 | Dashboard aggregates | Load the dashboard | Cards for all four domains render | PASS |
| X16 | Dashboard card gating | Load as each role | Only permitted cards appear | TODO |

## End-to-end integrity

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| X17 | Reserve → release cycle | Reserve 15, then release | 250→235→250 available, 12→27→12 reserved; two ledger rows | PASS |
| X18 | Delete RFQ returns stock | Delete an RFQ holding 30 reserved | available 70→100, reserved 30→0; quotes and reservations cascade; no orphans | PASS |
| X19 | Contact delete preserves RFQs | Delete a contact used by an RFQ and an audience | RFQ survives with `contact_id` NULL; audience row removed | PASS |
| X20 | Account delete is all-or-nothing | Delete an account that has RFQs | Blocked; interactions **not** destroyed | PASS |
| X21 | Backup → restore drill | `backup.php`, then `restore.php --into=scratch` | Restored copy identical: 12/12 accounts, 37/37 RFQs, 121/121 permissions, 9 constraints, 52 indexes | PASS |

## Security

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| X22 | **Privilege escalation** | As Sales, POST `permissions[3][]=admin.manage_permissions` to `/admin/permissions.php` with a valid session CSRF token | 403; matrix unchanged | PASS |
| X23 | Pre-fix confirmation | The same attack against the pre-fix code | Succeeded (302): Sales granted itself `admin.manage_permissions` + `admin.manage_users`, reached both admin screens, **and wiped every other role's permissions** — `savePermissions()` deletes before inserting | PASS (vulnerability confirmed, then fixed) |
| X24 | Role × route matrix | GET every module entry point as each of the five roles | 200/403 exactly per the seeded matrix; no 500s | PASS |
| X25 | Direct POST bypass | POST a mutating action as a role lacking that permission (RFQ delete, campaign edit, stock update) | 403 each; no state change | PASS |
| X26 | CSRF | POST any form without `_csrf` | 403 | PASS |
| X27 | Session cookie flags | Inspect `Set-Cookie` | `HttpOnly; SameSite=Lax`; `Secure` added under HTTPS | PASS |
| X28 | Logout is POST-only | GET `/logout.php`, then POST it | GET leaves the session intact; POST ends it | PASS |
| X29 | Security headers | Inspect response headers | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy — on both PHP pages and static assets, without duplication | PASS |
| X30 | Logs not web-reachable | GET `/admin/error_log`, `/.env`, `/.htaccess` | 403 each | PASS |
| X31 | No exception disclosure | Break a query, then call the list endpoint | Client sees only a request id; the server log holds the SQL error under that id | PASS |
| X32 | Permission revocation reaches live sessions | Revoke `customers.view` from a signed-in Sales user | 200 while cached, 403 within 60s | PASS |
| X33 | CSV formula injection | Export a row whose value begins `=`, `+`, `-` or `@` | Prefixed with `'`; ordinary values untouched | PASS |
| X34 | Login throttling | 7 failed logins in a row, then the correct password | Attempts 1–5 "Invalid login credentials"; 6th and 7th "Too many failed attempts". The **correct** password is also refused while locked out, so the lockout cannot be probed for validity | PASS |

## Not covered

| Gap | Note |
|-----|------|
| Concurrency | No test drives two simultaneous reservations of the same product. `SELECT … FOR UPDATE` should serialize them, but this is unproven. |
| Load / performance | Nothing measures the NFR1 "under three seconds" claim. It rests on index coverage, not measurement. |
| Browser / end-to-end | All HTTP-level testing was done with curl. No test drives real JavaScript, DataTables rendering, or responsive layout. |
| Unit tests | No framework is present; there are no unit tests for services or repositories. |
