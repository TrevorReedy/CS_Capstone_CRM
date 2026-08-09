# RFQ Tests

Manual test plan for the RFQ / quote / reservation module.

**Environment:** `docker compose down -v && docker compose up`, then load
`database/seed_dev_users.sql`. All test users share the password `password`.

**Last executed:** 2026-08-03 against a fresh volume. Every row below marked PASS
was actually run; rows marked TODO were not.

## Page loads

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| R1 | Pipeline list | GET `/modules/rfq/pipeline.php` | 200, DataTable populated | PASS |
| R2 | Detail | GET `/modules/rfq/detail.php?id=1` | 200, shows RFQ + account + quotes + reservations | PASS |
| R3 | Win rate | GET `/modules/rfq/win_rate.php` | 200 | PASS |
| R4 | PDF | GET `/modules/rfq/rfq_pdf.php?id=1` | 200, PDF body | PASS |

## Search — depends on the FULLTEXT indexes in `indexes.sql`

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| R5 | Title search | Search `Catheter` | 20 matches, no error | PASS |
| R6 | Account search | Search `Medical` | 9 matches | PASS |
| R7 | Short term | Search `IC` (below InnoDB min token size) | Falls back to LIKE; 16 matches, no error | PASS |
| R8 | **Index is load-bearing** | `DROP INDEX ft_rfqs_title`, then search `Catheter` | Search fails (safely — request id, no SQL leaked). Restoring the index returns 20 matches. Confirms `indexes.sql` is required, not decorative | PASS |

## CRUD and validation

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| R9 | Create RFQ | Valid form | 302, row created | PASS |
| R10 | Create quote | amount 5000, discount 500 | 302, saved as 5000.00 / 500.00 | PASS |
| R11 | Discount > amount | amount 100, discount 500 | 200 with "Discount cannot be greater than the quote amount." — **not** a 500 | PASS |
| R12 | End before start | start 2026-12-01, end 2026-01-01 | 200 with "Validity end date cannot be before the start date." | PASS |
| R13 | Negative amount | amount -5 | 200 with "Quote amount cannot be negative." | PASS |
| R14 | Valid quote after failures | amount 2500, discount 250 | 302, saved | PASS |

> R11–R13 are enforced twice: `RFQService::validateQuoteInput()` produces the
> message, and the `chk_quotes_*` constraints in `schema.sql` are the backstop if
> a write ever reaches the database another way.

## Reservations — inventory integrity

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| R15 | Valid reservation | Reserve 30 where 100 available | available 100→70, reserved 0→30 | PASS |
| R16 | **Over-reservation** | Reserve 500 where 100 available | "Insufficient inventory: 100 available, 500 requested." Stock unchanged, **no reservation row created** | PASS |
| R17 | Zero quantity | `quantity_reserved = 0` direct insert | Rejected by `chk_reservations_quantity` | PASS |
| R18 | Raise beyond stock | Edit reservation 20 (qty 30, 70 available) to 99999 | "Insufficient inventory: 70 available, 99969 more requested." | PASS |
| R19 | Release | Release reservation 21 | available 235→250, reserved 27→12 | PASS |
| R20 | Duplicate product on one RFQ | Insert the same (rfq_id, product_id) twice | Rejected by `uq_reservations_rfq_product` | PASS |
| R21 | Ledger | After R15 and R19 | `inventory_movements` holds `reserved` (−15) then `released` (+15) rows with user name and running totals | PASS |
| R22 | Delete RFQ | Delete RFQ 1, which held 30 reserved | available 70→100, reserved 30→0; quotes and reservations cascade; no orphans | PASS |

## Permissions

Verified by direct URL request, not by checking whether a button was hidden.

| # | Endpoint | Sales | Marketing | Inventory | Status |
|---|----------|:---:|:---:|:---:|:---:|
| R23 | `pipeline.php` | 200 | **403** | 200 | PASS |
| R24 | `detail.php?id=1` | 200 | **403** | 200 | PASS |
| R25 | `rfq_pdf.php?id=1` | 200 | **403** | 200 | PASS |
| R26 | `pipeline_data.php` (AJAX) | 200 | **403 JSON** | 200 | PASS |

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| R27 | Cross-role POST | As Marketing, POST `_action=delete` to `detail.php?id=1` **with a valid session CSRF token** | 403; RFQ still exists | PASS |
| R28 | Missing CSRF token | POST `create_quote.php` with no `_csrf` | 403 | PASS |

## Not covered

| Gap | Why it matters |
|-----|----------------|
| Concurrent reservations of the same product from simultaneous requests | `insertReservation()` takes `SELECT … FOR UPDATE` before validating availability, so it should serialize — but this has not been tested under real concurrency. |
| Stage transition rules | Any stage can move to any other. There is no state machine preventing e.g. Won → New, and no rule requiring a quote before Quoted. |
| Automatic release on Lost | Moving an RFQ to Lost does **not** release its reserved stock. It must be released manually. |
