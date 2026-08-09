# Handoff

What a new owner needs in order to take responsibility for this system. Read
[`KNOWN_LIMITATIONS.md`](KNOWN_LIMITATIONS.md) alongside it — this document says
how to run the thing, that one says what it does not do.

---

## Do these first

Nothing else in this document matters until these are done.

- [ ] **Rotate the database password.** The old one is in the git history
      (`docker-compose.yml` comments and an earlier `.env.example`). Deleting
      those files did not remove it — anyone who can clone the repository can
      recover it.
- [ ] **Change the seeded admin password.** `admin@typhoncath.test` / `password`
      is printed in the README and its bcrypt hash is in `seed.sql`. Change it in
      Admin → Users.
- [ ] **Confirm `seed_dev_users.sql` was never loaded on the server.** It creates
      five accounts — one per role — all sharing that same public password. Check:
      `SELECT email FROM users;` should show only real staff.
- [ ] **Rotate the FTP account password**, then update the `FTP_PASSWORD` GitHub
      secret.
- [ ] **Verify `APP_DEBUG=false`** in the server's `.env`. If it is true, PHP
      renders exception text — file paths and SQL — into the browser.
- [ ] **Take a backup and run a restore drill** (`--into=scratch`). Confirm it
      works before you need it.

---

## Accounts and access

Fill this in as you take ownership. It is the part no code can tell you.

| What | Where | Who holds it |
|---|---|---|
| Bluehost / cPanel login | | |
| FTP account | | |
| Domain registrar | | |
| GitHub repository | `github.com/sjasthi/typhoncath` | |
| Database credentials | server `config/database.php` | |

---

## Where things are

| | |
|---|---|
| Application code | `src/app/` — Controller / Service / Repository per module |
| Entry points | `src/public/` — one PHP file per page, no router |
| Views | `src/app/Modules/*/views/` and `src/app/Shared/` |
| Schema | `src/database/schema.sql` + `seed.sql` + `indexes.sql` |
| Server config | `config/database.php` and `.env` — **server only, never in git** |
| Logs | `src/storage/logs/application.log` |
| Backups | `src/storage/backups/` |
| Deployment | [`DEPLOYMENT.md`](DEPLOYMENT.md) |
| Architecture | [`writeup/`](writeup/) — fourteen numbered design documents |

There is no framework and no router. A URL maps to a file in `public/`; that
file requires `app/Core/bootstrap.php`, checks a permission, and calls a
controller. If you can read PHP you can follow any request end to end.

---

## Running it

```bash
docker compose up                       # http://localhost:8080
docker compose exec app composer test   # static harnesses + full PHPUnit suite
```

Docker is the only prerequisite. See [`setup.md`](setup.md).

---

## Tests and CI

| Layer | Command | Needs |
|---|---|---|
| Syntax | `composer lint` | nothing |
| CSRF / authorization wiring | `composer test:static` | nothing |
| Unit | `composer test:unit` | nothing |
| Integration | `composer test:integration` | MySQL 8 |

CI (`.github/workflows/ci.yml`) runs all four on every push and pull request.
Its `deploy` job ships to Bluehost on a push to `main`, but only after those
four jobs pass.

The two static harnesses are worth understanding, because they cover something
the PHPUnit suite cannot. They walk every entry point in `public/` and assert
that each POST form and handler is CSRF-protected, and that each route is
permission-gated *before* it dispatches. A new page that forgets either fails CI
rather than shipping quietly.

---

## Routine maintenance

**Weekly** — skim `storage/logs/application.log`; confirm the nightly backup
ran.

**Monthly** — `composer outdated` in `src/`; run the restore drill.

**When adding a feature** — see [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md).
The short version: branch, add tests, keep `schema.sql` and `migrations/` in
step, let CI pass, merge to `main` and it deploys.

---

## Things that will surprise you

- **`Super Admin` bypasses the permission matrix entirely.** This is deliberate:
  `savePermissions()` never writes rows for it, so the matrix cannot lock
  everyone out of the permission screen. Every other role, `Admin` included, is
  fully governed by the matrix.
- **Permission changes take up to 60 seconds** to reach an already-active
  session. It is a cache (`Permissions::REFRESH_AFTER_SECONDS`), traded against
  a query on every permission check.
- **Campaign sending is simulated.** Nothing leaves the system. `sent_count` is
  real (it counts audience rows); there is no mail or SMS integration.
- **Login throttling is per-session**, so it stops scripted guessing from one
  browser and nothing more.
- **`vendor/` is not in git.** It is a build artifact, produced by `composer
  install` during deploy. Copying the repository to a server by hand produces a
  site whose PDF export fatals. (Git previously held the Composer autoloader but
  not the dompdf library behind it, which was the same failure wearing a
  disguise — the directory looked present and was unusable.)
- **Nothing tracks which migrations have been applied.** No migrations table —
  you have to know. Write it down each time you upgrade.
- **Deleting a user who created RFQs or campaigns is blocked** by foreign keys.
  Intentional, to preserve history — but it means there is no clean offboarding
  action, since users cannot be disabled either.

---

## If you are handing this on again

Leave the next person: the filled-in access table above, the date and identity
of the last applied migration, the last verified restore drill, and anything in
`KNOWN_LIMITATIONS.md` that has changed.
