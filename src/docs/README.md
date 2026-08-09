# Documentation index

All project documentation lives here. Code-level READMEs stay at the root of
what they describe (`/README.md`, `/src/README.md`); manual test plans stay in
`/src/tests/` alongside the automated suite.

## Start here

| Document | What it covers |
|---|---|
| [`setup.md`](setup.md) | Local development setup (Docker) |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | Bluehost / cPanel deployment, the CI/CD pipeline, rollback, backups |
| [`HANDOFF.md`](HANDOFF.md) | Taking ownership: credentials to rotate, where things are, routine maintenance |
| [`KNOWN_LIMITATIONS.md`](KNOWN_LIMITATIONS.md) | What is simulated, unimplemented, or not production-grade. Read before any demo. |
| [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md) | Branching, running the tests, adding a migration |

## Architecture write-ups

[`writeup/`](writeup/) holds the fourteen numbered design documents — system
context, module architecture, ERD, navigation map, role/permission matrix, RFQ
pipeline and sequence, inventory reservation flow, campaign audience flow,
dashboard data flow, deployment architecture, CRUD matrix, and non-functional
qualities. Start at [`writeup/00_DIAGRAM_INDEX.md`](writeup/00_DIAGRAM_INDEX.md).

Diagram sources are in [`drawio/`](drawio/); exported images in [`img/`](img/).

## Module overviews

[`modules/`](modules/) — one overview per CRM module: admin, campaign, customer,
dashboard, inventory, rfq.

## Course and requirements documents

[`project/`](project/) — the source documents this build was written against:

| Document | What it is |
|---|---|
| `requirements.md` | The SRS. The gold standard for scope. |
| `ai_agent_for_crm.md` | Superseded earlier brief describing a different (Python/AI) project |
| `weekly_instruction_feedback.md` | Weekly instructor feedback |
| `typhon_cath_fp_weekly_todo.md` | Team weekly task tracker |
