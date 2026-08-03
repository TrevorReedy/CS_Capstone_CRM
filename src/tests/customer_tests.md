# Customer Tests

Manual test plan for accounts, contacts and interactions.

**Environment:** fresh volume + `database/seed_dev_users.sql`.
**Last executed:** 2026-08-03. Rows marked PASS were actually run; TODO rows were not.

## Page loads

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| C1 | Account list | GET `/modules/customer/accounts.php` | 200, DataTable populated | PASS |
| C2 | Account detail | GET `account_detail.php?id=2` | 200, shows contacts + interactions | PASS |
| C3 | Create form | GET `create_account.php` | 200 | PASS |
| C4 | PDF | GET `account_pdf.php?id=2` | 200 | PASS |
| C5 | Export CSV | GET `accounts_export.php?format=csv` | 200, CSV body | PASS |

## Search

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| C6 | Name search (FULLTEXT) | Search `Medical` | 3 matches | PASS |
| C7 | Name search | Search `Apex` | 1 match | PASS |
| C8 | Search by email / phone / tags | Enter each in the global search | Matches returned | TODO |
| C9 | Industry / source column filters | Use the dropdowns | List narrows | TODO |

## Interactions — the ENUM case bug

The create form once submitted `Call`/`Email`/`Note`/`Meeting` while the column
is `ENUM('call','email','note','meeting')`, so inserts blanked or failed under
strict mode and the edit dropdown never pre-selected. The lowercase form values
and the `strtolower()` normalisation are both present in `account_detail.php`;
these cases confirm the behaviour end to end.

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| C10 | Log each type | Create one interaction of each of the four types | All four save with the correct type | TODO |
| C11 | Edit pre-selects | Open an interaction for edit | Dropdown shows its current type | TODO |
| C12 | Case normalisation | POST `interaction_type=CALL` directly | Stored as `call` | TODO |

## Deletion integrity

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| C13 | Delete a contact used by an RFQ | Delete a contact referenced by an RFQ and a campaign audience | Contact removed; **RFQ survives** with `contact_id` NULL; audience rows cleaned | PASS |
| C14 | Delete an account with RFQs | Attempt it | Blocked: "cannot be deleted because it still has linked RFQs"; **nothing partially deleted** | PASS |
| C15 | Delete an account without RFQs | Delete a clean account | Removed; contacts and interactions cascade | TODO |

> C13/C14 are the regression cases for the old behaviour: `rfqs.contact_id` was
> `ON DELETE CASCADE`, so deleting one contact destroyed whole RFQs plus their
> quotes and reservations; and the account delete removed interactions *before*
> attempting the account, so a blocked delete had already destroyed the
> interaction history with nothing to roll it back.

## Permissions

| # | Endpoint | Sales | Marketing | Inventory | Status |
|---|----------|:---:|:---:|:---:|:---:|
| C16 | `accounts.php` | 200 | 200 | **403** | PASS |
| C17 | `account_detail.php?id=2` | 200 | 200 | **403** | PASS |
| C18 | `create_account.php` | 200 | **403** | **403** | PASS |
| C19 | `account_pdf.php?id=2` | 200 | 200 | **403** | PASS |
| C20 | `accounts_data.php` (AJAX) | 200 | 200 | **403** | PASS |
| C21 | `accounts_export.php` | 200 | 200 | **403** | PASS |

Marketing has `customers.view` but not `customers.create` — C18 is the check
that the write path is gated separately from the read path.

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| C22 | Per-action write gating | POST each of `update_account`, `delete_account`, `add_contact`, `update_contact`, `delete_contact`, `add_interaction`, `update_interaction`, `delete_interaction` as a role lacking that one permission | 403 each | TODO |
| C23 | Unknown POST marker | POST `account_detail.php` with no recognised marker | 403 (deny by default) | TODO |

## Not covered

- Dedicated contact list/detail screens — not implemented; contacts are reachable
  only through their account.
- Excel import — not implemented (see `../docs/KNOWN_LIMITATIONS.md`).
- Full server-side validation coverage on every account/contact field.
