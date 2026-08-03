# TyphonCath CRM

A lightweight internal CRM covering customers, the RFQ/quote pipeline, marketing
campaigns, and inventory, with a unified dashboard and role-based access.

Stack: PHP 8.2 (no framework — modular controller/service/repository), MySQL 8,
Bootstrap 5 + jQuery DataTables, Apache.

---

## Prerequisites

Install **Docker Desktop** for your OS:

- **Windows**: https://docs.docker.com/desktop/install/windows-install/
- **Mac**: https://docs.docker.com/desktop/install/mac-install/
- **Linux**: https://docs.docker.com/desktop/install/linux-install/

That's the only thing you need. No PHP, no MySQL, no XAMPP.

---

## Setup

### 1. Clone

```bash
git clone <repo-url>
cd typhoncath
```

### 2. Start

```bash
docker compose up
```

The first run takes a minute or two while Docker builds the PHP image. The
database is created, seeded and indexed automatically on first start.

### 3. Open

**http://localhost:8080**

### 4. Log in

| Field | Value |
|-------|-------|
| Email | `admin@typhoncath.test` |
| Password | `password` |

> **This is a demo credential and it is public — the hash is in `seed.sql`.**
> Fine for local development. Before any deployment, change it (Admin → Users)
> and replace the hash in `seed.sql`.

To exercise role-based access control, `database/seed_dev_users.sql` adds one
user per role, all with the same demo password:

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

---

## Configuration

Everything has a working default, so `docker compose up` needs no configuration.
To override, create a `.env` next to `docker-compose.yml`:

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
| Reset the database | `docker compose down -v && docker compose up` |
| Database console (dev only) | `docker compose --profile tools up` → http://localhost:8081 |

> **Reset warning**: `down -v` deletes the database volume. All data is wiped and
> re-seeded.

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

For an existing database, apply the migrations in numeric order. `020` is the
current head and brings a pre-existing database up to the constraint set now
declared in `schema.sql` — read its header first, as it repairs data.

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

Set `DB_SSL_MODE=DISABLED` if the client rejects a self-signed server
certificate. Run the `--into=scratch` drill periodically — an untested backup is
not a backup. The drill needs a database user that can create databases; the
application user deliberately cannot.

---

## Tests

```bash
php src/tests/csrf_coverage.php    # every POST form/handler is CSRF-protected
php src/tests/authz_coverage.php   # every endpoint is permission-gated
```

Both are static, need no server or database, and exit non-zero on failure, so
they suit CI or a pre-push hook. `src/tests/*.md` hold the manual test plans.

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

Read [`src/docs/KNOWN_LIMITATIONS.md`](src/docs/KNOWN_LIMITATIONS.md) before
demoing or deploying.
