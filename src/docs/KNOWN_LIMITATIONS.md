# Known limitations

An honest list of what this build does not do, what is simulated, and what a
reviewer should not read as production-grade. Written so nobody discovers these
during a demo.

Last reviewed: 2026-08-06.

---

## Simulated, not real

| Area | What actually happens |
|------|----------------------|
| **Campaign sending** | Nothing is sent. `CampaignService::simulateSend()` marks the campaign Sent and records a recipient count from the audience. No email or SMS leaves the system, and no mail service is integrated. |
| **SMS campaigns** | The `SMS Simulation` campaign type is a label. It behaves exactly like Email — there is no SMS gateway. The SRS lists SMS as optional simulation, so this matches scope. |
| **Open / click tracking** | **Removed.** Earlier builds stored simulated `open_rate` / `click_rate` values that looked like measurements but were generated numbers. Migration `019_drop_campaign_rates.sql` drops both columns rather than continue displaying fiction. `sent_count` is real (it counts audience rows). |
| **PDF list exports** | The "PDF" option on list views opens a print-formatted page and calls `window.print()` — the browser produces the PDF. Only the RFQ, customer and campaign *detail* reports generate a real server-side PDF (via dompdf). |

## Not implemented

- **Excel import.** The client described Excel as their source-of-record
  workflow, but there is no import path. Data entry is manual or via `seed.sql`.
- **Unified reports page.** Reporting is spread across dashboard cards and the
  win-rate view; there is no single consolidated reports screen.
- **Password reset / MFA.** An admin can change a password from Admin → Users;
  there is no self-service reset, no email verification, and no second factor.
- **Account deactivation.** Users can only be deleted, not disabled. Deleting a
  user who created RFQs or campaigns is blocked by foreign keys — intentionally,
  to preserve history, but it means there is no clean "offboard" action.
- **Product deletion has the same shape.** A product referenced by any
  reservation — active *or* historical — cannot be deleted, because the foreign
  key is RESTRICT. The service now says so explicitly instead of letting the
  database raise an integrity error, but there is still no way to retire a
  product that has ever been quoted.
- **Audit trail beyond inventory.** `inventory_movements` is a real append-only
  ledger. There is no equivalent for logins, permission changes, RFQ stage
  changes, or customer edits.

## Security posture

Addressed in this pass: endpoint authorization on every route, session cookie
flags, idle/absolute timeouts, POST-only logout, login throttling, security
headers, CSRF across all POSTs, no raw exception text in responses, and
protected logging. Both static harnesses (`csrf_coverage.php`,
`authz_coverage.php`) pass.

Still true:

- **Login throttling is per-session.** It stops scripted guessing from one
  browser. An attacker rotating cookies or IPs is not slowed down. A shared
  store (database or Redis) keyed by IP is the real fix.
- **CSP allows `unsafe-inline`.** Views and DataTables initialisers use inline
  script and style, so the policy cannot forbid them yet. It still blocks
  external script origins, framing, and cross-origin form posts.
- **Permission changes take up to 60 seconds** to reach an already-active
  session (`Permissions::REFRESH_AFTER_SECONDS`). It is a cache, traded against
  a query on every permission check.
- **`Super Admin` bypasses the permission matrix** by design — it is the
  break-glass role, and `savePermissions()` never writes rows for it so the
  matrix cannot lock everyone out of itself. Every other role, including
  `Admin`, is fully governed by the matrix.
- **The seeded admin password is public.** It is in `seed.sql` and in the
  README. Change it before any deployment.

## Data model

- **Tags are a comma-separated string** on `accounts.tags`, matched with
  `FIND_IN_SET`. That cannot use an index and will not scale; it also permits
  inconsistent spellings. Proper join tables are the fix.
- **Audience presets store `account_ids` / `contact_ids` as JSON text**, so
  there are no foreign keys protecting them. A preset can reference a deleted
  account.
- **Exports are unbounded.** "All" and the export endpoints `fetchAll()` the
  entire filtered set into memory. Fine at seed-data scale, a problem at tens of
  thousands of rows.

## Testing

What exists, and runs in CI on every push:

- **217 PHPUnit tests** (`composer test`) — a unit suite that needs nothing, and
  an integration suite against a real MySQL 8 loaded from `schema.sql`,
  `seed.sql` and `indexes.sql`.
- **`csrf_coverage.php` (185 checks) and `authz_coverage.php` (111 checks)** —
  static harnesses that read source rather than exercising a running app. They
  cover what the PHPUnit suite cannot: that *every* entry point is CSRF-
  protected and permission-gated, including ones nobody wrote a test for.

Still true:

- **No browser or end-to-end tests.** Nothing drives a real page. `http_forms.php`
  exercises live CSRF enforcement but must be pointed at a running instance by
  hand and is not in CI.
- **No load testing**, so the NFR1 "under three seconds" claim still rests on
  index coverage rather than on measurement.
- **The role × route matrix is verified statically, not behaviourally.**
  `authz_coverage.php` proves a gate exists and precedes dispatch; it does not
  prove the gate names the right permission for that page.
- `src/tests/*.md` remain manual test plans covering visual and browser
  behaviour.
- **One test is deliberately incomplete** and reports as such on every run: the
  schema-versus-migrations drift check (see Operations below).

## Operations

- **No migration runner.** Migrations are numbered `.sql` files applied by hand,
  and nothing records which have run — you have to know. Write it down at each
  upgrade; see [`HANDOFF.md`](HANDOFF.md).
- **Migrations cannot rebuild the schema.** `001`–`005` contain no SQL: those
  tables have only ever been defined in `schema.sql`. So `migrations/` is a
  changelog of changes *since* the original schema, not a build script, and a
  new database must come from `schema.sql` + `seed.sql` + `indexes.sql`.
  The consequence is that **nothing verifies the two agree** — a fresh install
  and an upgraded install could drift apart without anyone noticing. Closing
  this means reconstructing `schema.sql` as it stood before migration `006` and
  putting that into `001`–`005`; `MigrationTest` holds the check open as an
  incomplete test rather than pretending it passes.
- **Docker Compose is development-only.** No TLS, no reverse proxy, no secret
  management. The defaults in `docker-compose.yml` are throwaway values.
- **Credentials are in the git history.** Real hosting credentials were once
  committed in `docker-compose.yml` and `.env.example`. They have been removed
  from the working tree, but history is unchanged — **rotate them on the
  server.**
