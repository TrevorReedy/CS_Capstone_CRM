# Contributing

## Getting set up

```bash
git clone <repo-url>
cd typhoncath
docker compose up
```

Docker is the only prerequisite — no PHP, no MySQL, no XAMPP. The app comes up
on <http://localhost:8080>, and the database is created, seeded and indexed on
first start. Full detail in [`src/docs/setup.md`](src/docs/setup.md).

`vendor/` is gitignored, so install dependencies once after cloning (and again
whenever `composer.lock` changes):

```bash
docker compose exec app composer install
```

To exercise role-based access (the seed creates only a Super Admin, which
bypasses every permission check):

```bash
docker compose exec -T db mysql -uroot -pdevonly_root typhon_cath_crm \
  < src/database/seed_dev_users.sql
```

## Running the tests

```bash
docker compose exec app composer test              # everything
docker compose exec app composer test:unit         # no database, milliseconds
docker compose exec app composer test:integration  # needs MySQL
docker compose exec app composer test:static       # CSRF + authz wiring
docker compose exec app composer lint              # php -l over everything
```

Composer runs inside the container because the app requires PHP 8.2 and your
host may have something older.

The integration suite drops and rebuilds its database on every run, so it never
uses the one you develop against. It appends `_test` to whatever `DB_NAME` is
set to and refuses to drop a database not named that way, which is why running
it cannot cost you your local data. It connects as `DB_TEST_USER` /
`DB_TEST_PASS` (root, in `docker-compose.yml`) because creating a database needs
privileges the application's own user does not have and should not be given.

## Branching and review

- Branch off `main`. Name it for the work, not for yourself.
- Open a pull request into `main`. CI runs lint, the static harnesses, the unit
  suite and the integration suite; all four must pass.
- **A merge to `main` deploys to production.** See
  [`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md).

## Where code goes

```
src/app/Modules/<Module>/
    <Module>Controller.php    request handling, no SQL
    <Module>Service.php       business rules, validation
    <Module>Repository.php    SQL, and nothing else
    views/                    templates
src/public/modules/<module>/  entry points — one file per URL
```

There is no router. A URL is a file in `public/`. Each entry point requires
`app/Core/bootstrap.php`, checks a permission, then calls a controller.

## Rules that CI enforces

**Every POST form renders `Csrf::field()`, and every POST handler validates it**
(`require .../Middleware/csrf.php`, or `Csrf::check()`). `tests/csrf_coverage.php`
checks both across the whole codebase.

**Every entry point checks a permission before it dispatches** — not after.
`tests/authz_coverage.php` checks the gate exists *and* that it precedes the
first controller call, because a check that runs after the write has already
happened is decoration. It also verifies each permission string is one the seed
actually grants: a typo is deny-all for real roles but invisible to a Super
Admin, so it hides until someone else hits it.

A genuinely public route goes in that file's `PUBLIC_ROUTES` list with a reason.

**Nothing before the opening `<?php`** in core, config or entry-point files.
Whitespace or text there is output, and output kills every `header()` and
`session_start()` that follows. Views are exempt — opening with markup is their
job.

## Writing tests

Unit tests (`src/tests/Unit/`) take no database. Integration tests
(`src/tests/Integration/`) extend `Tests\IntegrationTestCase`, which rebuilds
the schema once per run and wraps each test in a transaction it rolls back.

Two things to know:

- MySQL implicitly commits on DDL. A test that issues `CREATE`/`ALTER`/`DROP`
  breaks isolation for everything after it — set `$useTransaction = false` and
  clean up after yourself.
- Repositories reach the database through the `Database::connection()` singleton
  rather than an injected handle, so `Database::swap()` is how a test points
  them somewhere else. `Tests\Support\NeverConnectingPdo` satisfies the
  constructors of repositories that resolve a connection eagerly (Admin,
  Campaign, RFQ) without opening one.

Prefer a test that would have caught a real bug over one that restates the
implementation.

## Database changes

Both files change together:

1. **`src/database/schema.sql`** — the definition a fresh install uses.
2. **`src/database/migrations/NNN_description.sql`** — the upgrade an existing
   database uses.

Number the migration one above the current head (`021`). Do not reuse a number:
files apply in filename order, and a collision leaves the order decided by how
the names happen to sort.

Never write `USE <database>` or `CREATE DATABASE` in a migration. cPanel
prefixes every database name, so a hardcoded name makes the migration
unrunnable on the deployment target. `MigrationTest` fails the build if one
appears.

Prefer idempotent migrations — check `information_schema` before altering — so
re-running one is safe. `015a_rename_interaction_summary.sql` is the model.

Add secondary and FULLTEXT indexes to **`src/database/indexes.sql`** as well;
that file is applied separately on a fresh install, and a missing FULLTEXT index
is a hard error, not slow search.

## Documentation

If a change alters how the system is deployed, operated or handed over, update
[`src/docs/DEPLOYMENT.md`](src/docs/DEPLOYMENT.md),
[`src/docs/HANDOFF.md`](src/docs/HANDOFF.md) or
[`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) in the same
pull request. `KNOWN_LIMITATIONS.md` in particular is only useful while it is
honest.
