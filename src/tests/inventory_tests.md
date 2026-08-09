# Inventory Tests

Manual test plan for products, stock, reservations and the movement ledger.

**Environment:** fresh volume + `database/seed_dev_users.sql`.
**Last executed:** 2026-08-03. Rows marked PASS were actually run; TODO rows were not.

## Page loads

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| I1 | Product list | GET `products.php` | 200, DataTable populated | PASS |
| I2 | Detail | GET `products.php?page=detail&id=1` | 200 | PASS |
| I3 | Stock form | GET `products.php?page=stock&id=1` | 200 | PASS |
| I4 | Reservations | GET `products.php?page=reservations` | 200 | PASS |
| I5 | Ledger | GET `products.php?page=ledger` | 200 | PASS |
| I6 | Export CSV | GET `products_export.php?format=csv` | 200 | PASS |

## Stock adjustment

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| I7 | Valid update | As Inventory Manager set available 100 → 120 | 302; stored 120; ledger row `manual_adjustment`, delta +20, `available_quantity_after` 120, user "Inventory Tester" | PASS |
| I8 | Negative quantity | Set available to −5 | Rejected by `InventoryService::updateStock()`; `chk_inventory_available` is the backstop | TODO |
| I9 | Reserved is read-only | Inspect the stock form's fields | Only `product_id` and `available_quantity` are submitted — reserved is owned solely by the RFQ reservation flow | PASS |

## Product CRUD and validation

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| I10 | Create product | Valid form | Product + inventory + `created` ledger row, in one transaction | TODO |
| I11 | Duplicate SKU | Reuse an existing SKU | "SKU … is already in use by another product." | TODO |
| I12 | Negative price | price = −10 | Rejected by the service; `chk_products_price` rejects it at the database too | PASS (constraint only) |
| I13 | Delete with active reservations | Delete a product holding Reserved rows | Blocked with a business message, not a raw PDO error | TODO |
| I14 | Transaction rollback | Force `logMovement` to fail mid-create | No orphan product without an inventory or ledger row | TODO |

> I10/I14 exercise `InventoryService::transactional()`. Before it existed,
> product + inventory + ledger were three independent writes, so a failure partway
> through left a product with no stock row, or stock that disagreed with the
> ledger meant to audit it.

## Ledger

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| I15 | Manual adjustment logged | After I7 | `manual_adjustment` row with running totals | PASS |
| I16 | **RFQ reservations logged** | Reserve then release from the RFQ module | `reserved` (−15) then `released` (+15) rows, with running totals and the acting user | PASS |
| I17 | Ledger filters | Filter by product and by movement type | List narrows | TODO |
| I18 | Append-only | Look for any UI path that edits or deletes a movement | None exists | PASS |

> I16 is a regression case: the RFQ module never wrote to `inventory_movements`,
> so the ledger silently disagreed with the stock levels it was supposed to
> explain. `RFQRepository::logInventoryMovement()` now writes inside the same
> transaction as the stock change.

## Permissions

| # | Endpoint (GET) | Sales | Marketing | Inventory | Status |
|---|----------------|:---:|:---:|:---:|:---:|
| I19 | `products.php` | 200 | **403** | 200 | PASS |
| I20 | `?page=detail&id=1` | 200 | **403** | 200 | PASS |
| I21 | `?page=stock&id=1` | 200 | **403** | 200 | PASS |
| I22 | `?page=ledger` | 200 | **403** | 200 | PASS |
| I23 | `?page=reservations` | 200 | **403** | 200 | PASS |
| I24 | `products_data.php` (AJAX) | 200 | **403 JSON** | 200 | PASS |

**View and mutate are gated separately.** Sales holds `inventory.view` but not
`inventory.update_stock`:

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| I25 | Sales can view the stock form | GET `?page=stock&id=1` | 200 | PASS |
| I26 | **Sales cannot submit it** | POST `product_id=1&available_quantity=99999` with a valid session CSRF token | 403; quantity unchanged at 120 | PASS |
| I27 | Inventory Manager can submit it | The same POST as Inventory Manager | 302; applied | PASS |

## Not covered

- Concurrent stock adjustment from simultaneous requests.
- Release/convert initiated from the Inventory reservations page (the RFQ-side
  equivalents are covered in `rfq_tests.md`).
- Low-stock threshold behaviour driving the list's status filter.
