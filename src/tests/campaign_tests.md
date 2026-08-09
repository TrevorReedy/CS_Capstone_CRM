# Campaign Tests

Manual test plan for campaigns, audiences and campaign analytics.

**Environment:** fresh volume + `database/seed_dev_users.sql`.
**Last executed:** 2026-08-03. Rows marked PASS were actually run; TODO rows were not.

## Page loads

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| M1 | Campaign list | GET `campaigns.php` | 200, DataTable populated | PASS |
| M2 | Detail | GET `detail.php?id=1` | 200 | PASS |
| M3 | Edit | GET `edit.php?id=1` | 200 | PASS |
| M4 | Create | GET `create.php` | 200 | PASS |
| M5 | Audience | GET `audience.php?campaign_id=1` | 200 | PASS |
| M6 | PDF | GET `campaign_pdf.php?id=1` | 200 | PASS |
| M7 | Momentum chart data | GET `momentum_data.php?range=12w` | 200 JSON | PASS |
| M8 | Export CSV | GET `campaigns_export.php?format=csv` | 200 | PASS |

## Authorization bypass — the edit POST

`edit.php` called `handleUpdatePost()` *before* `CampaignController::edit()`, and
the `campaigns.edit` check lived inside `edit()`. Since `edit()` runs only on the
GET path, a user holding `campaigns.view` alone could POST an update that was
never authorized. The gate now sits at the entry point, above the POST branch.

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| M9 | **View-only user POSTs an update** | Revoke `campaigns.edit` from Marketing, then POST `campaign_name=PWNED` to `edit.php?id=1` with a valid session CSRF token | 403; name still "Q2 Hospital Outreach" | PASS |
| M10 | With permission | Restore `campaigns.edit` and repeat | 302; the update applies | PASS |
| M11 | Regression guard | `php tests/authz_coverage.php` | Its ORDER check fails if any gate is moved below a dispatch | PASS |

## CRUD and validation

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| M12 | Create campaign | Valid form | Saved | TODO |
| M13 | Missing name | Blank name | Validation error | TODO |
| M14 | Scheduled without a date | status = Scheduled, no `scheduled_at` | Should be rejected — **nothing enforces this today** | TODO |
| M15 | Delete | Delete a campaign | Removed; audience rows cascade | TODO |

## Audience

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| M16 | Add a segment | Select accounts/contacts/tag filter | Rows written to `campaign_audience` | TODO |
| M17 | Edit a segment | Change an existing segment | Old rows replaced by the new ones | TODO |
| M18 | **Replace is atomic** | Force the insert half to fail during an edit | Old audience still present — not deleted then lost | TODO |
| M19 | Preview | POST to `preview_audience.php` | JSON count matching the filters | TODO |
| M20 | Deleted contact | Delete a contact that sits in an audience | Audience row cascades away; campaign survives | PASS |

> M18 exercises the transaction added to `handleAudiencePost()`. Segment editing
> is delete-then-insert; without a transaction a failure between the two left the
> campaign with no audience at all.

## Simulated send

| # | Test | Steps | Expected | Status |
|---|------|-------|----------|:---:|
| M21 | Send | Trigger a send on a campaign with an audience | Status → Sent, `sent_count` = audience size. **Nothing is actually delivered** | TODO |
| M22 | Empty audience | Send with no audience rows | `sent_count` should be 0 — known weak spot; `simulateSend()` can assign a count regardless | TODO |

Open and click rates were removed in `019_drop_campaign_rates.sql` — they were
generated numbers presented as measurements. See `../docs/KNOWN_LIMITATIONS.md`.

## Permissions

| # | Endpoint | Sales | Marketing | Inventory | Status |
|---|----------|:---:|:---:|:---:|:---:|
| M23 | `campaigns.php` | **403** | 200 | **403** | PASS |
| M24 | `detail.php?id=1` | **403** | 200 | **403** | PASS |
| M25 | `edit.php?id=1` | **403** | 200 | **403** | PASS |
| M26 | `create.php` | **403** | 200 | **403** | PASS |
| M27 | `audience.php?campaign_id=1` | **403** | 200 | **403** | PASS |
| M28 | `momentum_data.php` | **403 JSON** | 200 | **403 JSON** | PASS |

## Not covered

- Real delivery, open tracking and click tracking — not implemented by design.
- Tag matching at scale: `FIND_IN_SET` over the comma-separated `accounts.tags`
  column cannot use an index.
- Audience preset integrity: presets store `account_ids`/`contact_ids` as JSON
  text with no foreign keys, so a preset can reference a deleted account.
