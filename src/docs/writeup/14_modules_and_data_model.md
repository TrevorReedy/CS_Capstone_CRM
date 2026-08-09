# 14 — Modules, Functions and Data Model (as built)

Two pages in one file: [`../drawio/14_modules_and_data_model.drawio`](../drawio/14_modules_and_data_model.drawio).

| Page | Image | Shows |
|---|---|---|
| 1 | [`14a_modules_and_functions.png`](../img/14a_modules_and_functions.png) | The six modules as Controller / Service / Repository |
| 2 | [`14b_data_model_cardinality.png`](../img/14b_data_model_cardinality.png) | Fifteen tables with crow's-foot cardinality |

Unlike `01`–`13`, which are briefs describing a diagram to be drawn, this one was
generated from `app/Modules/` and `database/schema.sql` as they actually are. It
records what was built, so it goes stale the moment either changes — see
**Regenerating** below.

---

## Page 1 — Modules and functions

Every module is the same three-layer stack, and the diagram exists mostly to show
that the pattern really is uniform:

- **Controller** — one public method per page or POST handler. Reached directly
  from a file in `public/`; there is no router.
- **Service** — validation and business rules. The layer that owns transactions.
- **Repository** — all SQL. Nothing else in the module writes a query.

Method lists are representative, not exhaustive; the italic line under each box
gives the count not shown (`RFQRepository` alone has 33 public methods).

Two things on this page are not uniform, and both are deliberate:

- **Customer** has a `CustomerService` with no public methods. Its controller has
  one method, `index()`. The module is read-only today; the service exists so
  that the first business rule has an obvious home.
- **Dashboard** has a fourth class. `DashboardController::index()` builds
  `DashboardCard` instances, and each card reads `DashboardService` — so the flow
  is Controller → Card → Service → Repository. `DashboardService` is also the one
  service that reaches outside its own module: it composes `RFQRepository` and
  `CampaignRepository` directly (the dashed purple edges) rather than duplicating
  their queries, and memoises one campaign-stats read that four cards share.

The **Core** band is drawn without arrows on purpose. Every repository obtains its
handle from `Database::connection()` and every controller passes through
`Permissions` and `Csrf`; drawing thirty-odd edges to say so would obscure the
module structure rather than explain it.

## Page 2 — Data model and cardinality

Cardinality is read off the actual constraints in `database/schema.sql`, not from
intent:

| Notation | Means |
|---|---|
| `1 : N` | the FK column is `NOT NULL` |
| `0..1 : N` | the FK column is `NULL`-able |
| `1 : 1` | the FK column is `UNIQUE` |

Points worth knowing:

- **`products` → `inventory` is 1:1**, enforced by `UNIQUE` on
  `inventory.product_id`. Stock levels are a separate row from the product.
- **`rfqs.account_id` and `contact_id` are both nullable** (migration `010`), so
  an RFQ can exist before it is attached to a customer.
- **`campaign_audience` carries a nullable `account_id` *and* a nullable
  `contact_id`** — one row targets either an account or a contact, which is why
  neither can be `NOT NULL`.
- **`inventory_movements.product_id` is nullable and the row snapshots
  `product_name`/`sku`.** The ledger has to survive the product being deleted.
- **`roles.owner_user_id` has no `FOREIGN KEY`** — it is a plain nullable `INT`.
  The diagram labels it rather than drawing a relationship that does not exist.
- **`accounts.parent_account_id`** is a self-reference, giving parent/child
  accounts.

---

## Regenerating

The `.drawio` is generated, not hand-edited — edits to it are lost on the next run.

```bash
python3 docs/drawio/14_generate.py          # rewrites the .drawio (both pages)

# then re-export the images (draw.io desktop; -p is 1-based)
drawio -x -f png -s 2 -b 12 -p 1 -o docs/img/14a_modules_and_functions.png \
       docs/drawio/14_modules_and_data_model.drawio
drawio -x -f png -s 2 -b 12 -p 2 -o docs/img/14b_data_model_cardinality.png \
       docs/drawio/14_modules_and_data_model.drawio
```

Method lists and table columns are literals in `14_generate.py`; update them there
when a module or the schema changes. Colours follow the convention in
[`00_DIAGRAM_INDEX.md`](00_DIAGRAM_INDEX.md) — Customer blue, RFQ orange, Campaign
purple, Inventory green, Dashboard grey.
