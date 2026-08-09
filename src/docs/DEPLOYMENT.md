# Deployment — Bluehost / cPanel

How this application gets onto the live server, and what to do when it needs to
come back off. For local development see [`setup.md`](setup.md).

---

## The shape of it

| | |
|---|---|
| Host | Bluehost shared hosting (cPanel) |
| PHP | **8.2** — set in cPanel's MultiPHP Manager. `composer.json` requires it. |
| Database | MySQL 8, created through cPanel |
| Transport | FTPS, automated by the `deploy` job in `.github/workflows/ci.yml` |
| Document root | points at the app's `public/` directory, **not** `public_html` |

The document root arrangement is the important part:

```
/home/<cpaneluser>/
└── typhoncath/            <- FTP_SERVER_DIR points here
    ├── app/               <- not web-reachable
    ├── config/            <- not web-reachable  (holds database.php)
    ├── database/          <- not web-reachable
    ├── storage/           <- not web-reachable  (logs, backups)
    ├── vendor/            <- built by CI
    ├── .env               <- not web-reachable
    └── public/            <- THE DOCUMENT ROOT
        ├── index.php
        └── assets/
```

Only `public/` is served. `app/`, `config/`, `database/` and `.env` sit one level
above the web root, so no request can reach them regardless of what `.htaccess`
says. `public/.htaccess` is defence in depth on top of that, not the primary
control.

To set this up in cPanel: **Domains** → your domain → **Document Root** →
`typhoncath/public`.

> If you cannot change the document root on your plan, the fallback is to put
> everything in `public_html` and rely on `public/.htaccess` alone. That is
> strictly worse — a single Apache misconfiguration exposes `.env` and the whole
> source tree — so change the document root if you possibly can.

---

## First-time setup

### 1. PHP version

cPanel → **MultiPHP Manager** → select the domain → **PHP 8.2**.

Confirm the required extensions are on (cPanel → **Select PHP Version** →
Extensions): `pdo`, `pdo_mysql`, `mbstring`, `dom`.

### 2. Database

cPanel → **MySQL Databases**:

1. Create a database. cPanel forces a prefix, so `typhoncath` becomes something
   like `cpaneluser_typhoncath` — **note the full name**, you need it twice below.
2. Create a user and give it **ALL PRIVILEGES** on that database.
3. Record the credentials somewhere the team can reach; they go into
   `config/database.php` next and nowhere else.

### 3. Load the schema

cPanel → **phpMyAdmin** → select the database → **Import**, and run these three
files **in this order**:

1. `src/database/schema.sql` — tables, keys, CHECK constraints
2. `src/database/seed.sql` — the role/permission matrix and demo data
3. `src/database/indexes.sql` — **not optional**

`indexes.sql` creates every secondary and FULLTEXT index. Without it, RFQ,
account and campaign search do not degrade — they fail outright with *"Can't
find FULLTEXT index matching the column list."*

**Do not load `seed_dev_users.sql`.** It creates five accounts sharing one
password that is published in the README. The deploy workflow excludes it from
upload for the same reason.

> **Never build a production database from `database/migrations/`.** Migrations
> 001–005 contain no SQL — those tables have only ever been defined in
> `schema.sql` — so the chain cannot build a database from scratch. It is a
> changelog of changes *since* the original schema, not a build script. See
> [Upgrading an existing database](#upgrading-an-existing-database).

### 4. Server-side configuration

Two files live only on the server. Both are gitignored, neither is ever
deployed, and the deploy workflow explicitly excludes them so a sync cannot
delete them.

Create `config/database.php`:

```php
<?php
return [
    'host'     => 'localhost',
    'port'     => '3306',
    'database' => 'cpaneluser_typhoncath',
    'username' => 'cpaneluser_crmuser',
    'password' => '...',
];
```

Create `.env` (copy `.env.example` and edit):

```
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

`APP_DEBUG=false` matters: it is what stops PHP rendering exception text into
the response body. `SESSION_SECURE=true` forces the Secure flag on the session
cookie.

### 5. Writable directories

`storage/logs/` and `storage/backups/` must be writable by the web user, or the
application silently falls back to Apache's error log:

```
chmod 750 storage/logs storage/backups
```

### 6. GitHub secrets

Repository → **Settings** → **Secrets and variables** → **Actions**:

| Secret | Value |
|---|---|
| `FTP_HOST` | e.g. `ftp.yourdomain.com` |
| `FTP_USERNAME` | the cPanel FTP account |
| `FTP_PASSWORD` | that account's password |
| `FTP_SERVER_DIR` | path **containing** `public/`, with a trailing slash — e.g. `/typhoncath/` |

`FTP_SERVER_DIR` is not `public_html`.

Optionally add a manual gate: **Settings** → **Environments** → `production` →
**Required reviewers**. The deploy job already targets that environment.

---

## Deploying

### Automatic

Any push to `main` deploys.

> **CI and deploy are separate workflows, and both fire on the same push.** They
> run in parallel — the deploy does **not** wait for the tests, and a red CI run
> does not stop it. Two ways to close that, either of which can be turned on in
> repository settings without touching the workflow files:
>
> - **Branch protection** (Settings → Branches → `main`): require the four CI
>   checks to pass before a pull request can merge. Anything reaching `main`
>   through a PR has then passed CI. A direct push to `main` still bypasses it,
>   so pair this with "do not allow bypassing".
> - **A required reviewer** (Settings → Environments → `production`): the deploy
>   job already targets that environment, so adding a reviewer makes every
>   deploy wait for a human.
>
> To make the dependency automatic instead, move the deploy job into `ci.yml`
> with `needs: [lint, static, unit, integration]` and a
> `if: github.ref == 'refs/heads/main'` guard.

### Manual

Actions → **Deploy to Bluehost** → **Run workflow**. Tick **dry run** to see
what would change without uploading anything.

### The first deploy must be a dry run

`lftp mirror --delete` compares the local tree against the server and removes
anything on the server that is not present locally. On a site that has only ever
been hand-uploaded, that difference can be large. Run it once with **dry run**
ticked and read the log — it prints every file it would send and every file it
would remove — before letting it write.

### How the transfer works

No third-party action is involved. The job installs `lftp` (Debian/Ubuntu
package, in the runner's own apt repositories) and drives it directly, so the
only things that ever see the FTP credentials are the GitHub runner and lftp.

The password is passed through the `LFTP_PASSWORD` environment variable and read
by lftp's `--env-password`, so it is never a command-line argument (which would
be visible in the process list) and never appears in the script body.

Encryption is enforced rather than negotiated: `ftp:ssl-force` refuses to fall
back to plaintext, `ftp:ssl-protect-data` encrypts the data channel and not just
the login, and `ssl:verify-certificate` requires a valid certificate. If Bluehost
ever presents a bad certificate the deploy fails instead of quietly downgrading.

### What the pipeline does that FileZilla cannot

It runs `composer install --no-dev --optimize-autoloader` first. **`vendor/` is a
build artifact and is gitignored**, and dompdf is a hard runtime dependency — so
a manual mirror of the repository produces a site whose PDF exports fatal on the
first request. This is the main reason the pipeline exists.

This was previously worse than it looked: git tracked the Composer autoloader
and two dompdf font files, but not the dompdf library itself. A hand-deployed
copy therefore had a `vendor/` directory that appeared complete and whose
autoloader pointed at classes that did not exist.

### What it will never touch

The `EXCLUDES` list in the `deploy` job is load-bearing, because `mirror --delete`
removes remote files that are absent locally. They are POSIX regexes matched
against the path relative to the deploy directory. Protected:

- `.env`, `config/database.php` — server-owned configuration
- `public/uploads/**` — user data, not recoverable from git
- `storage/logs/**`, `storage/backups/**`, `database/backups/**` — runtime state

Also excluded, as they have no business on a public host: `tests/`, `docs/`,
`composer.json`, `composer.lock`, `phpunit.xml`, `database/seed_dev_users.sql`.

---

## Verifying a deploy

1. Log in.
2. Open the dashboard — it runs 21 aggregate queries; a schema problem shows here first.
3. Search an account by name. This exercises FULLTEXT, i.e. proves `indexes.sql` loaded.
4. **Open an RFQ and export its PDF.** This is the one that proves the `vendor/`
   build actually arrived.
5. Check `storage/logs/application.log` is empty of new entries.

---

## Rolling back

There is no server-side version history, so a rollback is a redeploy of the
previous commit:

```bash
git checkout main
git revert <bad-commit>     # or: git reset --hard <last-good-commit>
git push
```

Or run the deploy workflow manually from a previous tag. Either way the mirror
overwrites the live files.

**A rollback does not undo database changes.** If the bad deploy applied a
migration, restore from a backup — see below.

---

## Upgrading an existing database

For a database that already exists, apply the files in `database/migrations/` in
filename order, starting from the first one not yet applied.

Notes:

- **Nothing tracks which migrations have been applied.** There is no migrations
  table; you have to know. Record it in the handover notes each time.
- Files are ordered by name. `015a_rename_interaction_summary.sql` sorts after
  `015_create_inventory_movements.sql` deliberately — it must run before 018 and
  020, which reference the renamed column.
- `021_campaign_dashboard_indexes.sql` is the current head.
- `020_integrity_constraints.sql` **repairs data** before adding constraints.
  Read its header and take a backup first.
- No migration names a database, so they apply to a cPanel-prefixed database
  unchanged. (Six of them used to hardcode `USE typhon_cath_crm`, which made the
  upgrade path impossible to follow on this host.)

---

## Backups

```bash
php database/backup.php                              # timestamped dump
php database/restore.php <dump.sql>                  # restore over the database
php database/restore.php <dump.sql> --into=scratch   # non-destructive drill
```

Schedule the dump in cPanel → **Cron Jobs**:

```
0 2 * * *  /usr/local/bin/php /home/<cpaneluser>/typhoncath/database/backup.php >> /home/<cpaneluser>/backup.log 2>&1
```

Run the `--into=scratch` drill periodically. An untested backup is not a backup.
The drill needs a user that can create databases; the application user
deliberately cannot.

---

## Troubleshooting

**"Can't find FULLTEXT index matching the column list"**
`indexes.sql` was not applied. Import it in phpMyAdmin.

**PDF export throws a 500**
`vendor/` is missing or incomplete. Re-run the deploy workflow; do not copy
`vendor/` by hand.

**A blank white page**
`APP_DEBUG=false` is doing its job and hiding an exception. Read
`storage/logs/application.log`.

**Everything 500s right after a deploy**
Check the PHP version in MultiPHP Manager. An 8.1 runtime against an 8.2
codebase fails on syntax it does not recognise.

**Session drops on every page**
Output before headers. Run `php tests/csrf_coverage.php` locally — its LINT
check exists for exactly this.

**Nobody can log in after changing the permission matrix**
`Super Admin` bypasses the matrix by design and cannot be locked out. Log in as
that role and fix the grants.
