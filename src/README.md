# `src/` — the application

This directory is the application itself. Everything above it (`docker-compose.yml`,
`Dockerfile`, `.github/`) is tooling for running and shipping it.

**The project README is one level up: [`../README.md`](../README.md)** — what the
system is, how to run it, how the architecture fits together, and how CI/CD
works. Documentation index: [`docs/README.md`](docs/README.md).

## Layout

| | |
|---|---|
| `public/` | **The document root** — the only web-accessible directory. One PHP file per URL |
| `app/Core/` | Auth, database, permissions, CSRF, pagination, DataTable, PDF |
| `app/Middleware/` | Auth and CSRF guards, included per entry point |
| `app/Modules/` | The CRM modules: Customer, RFQ, Campaign, Inventory, Dashboard, Admin |
| `app/Shared/` | Layout partials — header, sidebar, footer, 403 |
| `config/` | `database.php` — **server-only, never committed** |
| `database/` | `schema.sql`, `seed.sql`, `indexes.sql`, `migrations/`, backup and restore scripts |
| `docs/` | All project documentation |
| `storage/` | Logs and backups. Not web-accessible |
| `tests/` | PHPUnit suites, the CSRF and authorization static harnesses, manual test plans |

`app/`, `config/`, `database/` and `storage/` must sit outside the web root — see
[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Running the tests

```bash
docker compose exec app composer test        # static harnesses + full PHPUnit suite
docker compose exec app composer test:unit   # no database
docker compose exec app composer lint        # php -l over everything
```

Composer runs inside the container because the app requires PHP 8.2. See
[`../CONTRIBUTING.md`](../CONTRIBUTING.md).
