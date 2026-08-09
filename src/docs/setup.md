# TyphonCath CRM — Local Setup Guide

For deploying to the live server, see [`DEPLOYMENT.md`](DEPLOYMENT.md).

## Prerequisites

Install **Docker Desktop** for your OS:

- **Windows**: https://docs.docker.com/desktop/install/windows-install/
- **Mac**: https://docs.docker.com/desktop/install/mac-install/
- **Linux**: https://docs.docker.com/desktop/install/linux-install/

That's the only thing you need to install. No PHP, no MySQL, no XAMPP.

---

## Setup Steps

### 1. Clone the repository

```bash
git clone <repo-url>
cd typhoncath
```

### 2. Start the project

```bash
docker compose up
```

The first run will take a minute or two — Docker is downloading images and building the PHP container. Subsequent starts are fast.

When you see a line like `app-1 | AH00558: apache2: Could not reliably determine...` the server is ready.

### 3. Open the app

Go to **http://localhost:8080** in your browser.

### 4. Log in

Use the demo credentials seeded into the database:

| Field | Value |
|-------|-------|
| Email | `admin@typhoncath.test` |
| Password | `password` |

> This credential is public — the bcrypt hash is in `seed.sql` and the password
> is in the README. Fine locally. Change it before any deployment; see
> [`HANDOFF.md`](HANDOFF.md).

To exercise role-based access control, load one user per role. The seed alone
creates only a Super Admin, and that role bypasses every permission check, so
every page looks reachable:

```bash
docker compose exec -T db mysql -uroot -pdevonly_root typhon_cath_crm \
  < src/database/seed_dev_users.sql
```

All five share the same demo password. Development only — never load this into a
deployed environment.

---

## Daily Use

| Task | Command |
|------|---------|
| Start the app | `docker compose up` |
| Start in background | `docker compose up -d` |
| Stop the app | `docker compose down` |
| View logs | `docker compose logs -f` |
| Reset the database | `docker compose down -v` then `docker compose up` |

> **Reset warning**: `docker compose down -v` deletes the database volume. All data is wiped and re-seeded from scratch. Use this if the DB gets into a bad state.

---

## Troubleshooting

**Port 8080 already in use**
Something else on your machine is using port 8080. Either stop that process, or change `"8080:80"` to `"8081:80"` in `docker-compose.yml` and visit http://localhost:8081.

**Port 3306 already in use**
You have a local MySQL running. Set `DB_HOST_PORT=3307` in a `.env` file next to `docker-compose.yml` — no need to edit the compose file itself. The app is unaffected either way; that port is only for connecting with a DB client like TablePlus or DBeaver.

**Database connection error on first boot**
The app container sometimes starts before MySQL is fully ready. Run `docker compose restart app` to fix it.

**Schema changes / new migrations**
If someone adds a migration file, run `docker compose down -v && docker compose up` to rebuild the database from scratch.

---

## Connecting a Database Client (optional)

If you want to browse the database directly with TablePlus, DBeaver, or MySQL Workbench:

| Setting | Value |
|---------|-------|
| Host | `127.0.0.1` |
| Port | `3306` (or `DB_HOST_PORT`) |
| Database | `typhon_cath_crm` |
| Username | `crm_user` |
| Password | `devonly_change_me` |

These are the Compose defaults. Override them with a `.env` next to
`docker-compose.yml`.
