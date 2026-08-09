# Typhon Cath CRM — FP Weekly To-Do List

## Purpose

This document compiles the project requirements, meeting clarification notes, and FP3–FP10 weekly planning notes into one weekly to-do list for the Typhon Cath CRM project.

The goal is to make the weekly responsibilities easy to upload, review, and track during the remaining FP iterations.

---

## Important Team Note

**Jonah was originally listed as the owner for Digital Campaign Management / Marketing User UI. Jonah has since been removed from the group.**

Jonah's section is still included below for traceability because the original FP weekly list included him. Any work listed under Jonah / Marketing should be treated as **unassigned work that needs to be redistributed** among the remaining group members or discussed with the instructor.

Active listed members:

- **Max** — Customer Management
- **Trevor** — RFQ / Pipeline Management
- **Casey** — Inventory Management
- **All active members** — Integration layer, dashboard, repo structure, testing, documentation, deployment
- **Jonah / Marketing** — Removed from group; previous tasks preserved for record only

---

# 1. Project Overview

## CRM Purpose

The CRM system is designed to support Typhon Cath's internal business workflow by centralizing customer information, RFQs, marketing campaigns, inventory, and reporting in one web-based system.

Primary goals:

- Centralize customer and business interaction data
- Track sales opportunities through the RFQ lifecycle
- Manage digital marketing campaigns
- Maintain inventory visibility for products/services
- Provide a unified operational dashboard for decision-making

---

## Required Technology Stack

Frontend:

- HTML5
- CSS3
- JavaScript
- jQuery
- Bootstrap

Backend:

- PHP

Database:

- MySQL

Additional tools:

- Git / GitHub
- Apache or Nginx web server

---

## Main Project Modules

| Module | Original Owner | Current Status | Main Responsibility |
| --- | --- | --- | --- |
| Customer Management | Max | Active | Accounts, contacts, customer profiles, interaction history |
| RFQ / Pipeline Management | Trevor | Active | RFQ creation, pipeline stages, quotes, deal tracking |
| Digital Campaign Management | Jonah | **Removed from group** | Campaign creation, segmentation, campaign metrics |
| Inventory Management | Casey | Active | Product catalog, stock tracking, inventory allocation to RFQs |
| Integration Layer | All | Active members | Shared database, dashboard, reports, RBAC, internal APIs |

---

# 2. Client / Requirement Clarifications

These notes should guide the weekly implementation work.

## Account and Contact Rules

- One account can have multiple contacts.
- Accounts can exist without contacts.
- Contacts should not exist without a corresponding account.
- A contact moving to a different company is not a major expected use case.

## Account Sources

Accounts may come from:

- Existing business knowledge
- Internet search
- Cold outreach / sales discovery
- Imported Excel data

## Pipeline Dashboard

The pipeline dashboard should not rely only on one opportunity pipeline view. It should account for multiple status categories, including:

- Open
- Won
- Lost

## Starting Data

The original manual workflow uses Excel. The CRM should support the idea that Excel data can guide schema design and possibly be imported later.

## Hosting / Deployment Concerns

The client or site owner may need to provide hosting or FTP/platform access. The group should plan how weekly work will be pushed somewhere the client/instructor can review it.

---

# 3. Required User Roles

## Super Admin

Super Admin can access everything:

- Dashboard
- Customers
- RFQs / Pipeline
- Campaigns
- Inventory
- Reports
- Admin users
- Roles
- Settings

## Admin

Admin is the business-manager style role:

- View dashboard summaries
- Work across customers, RFQs, campaigns, inventory, and reports
- Does not necessarily need deep system configuration features unless required

## Sales User

Sales User focuses on:

- Customer lookup
- RFQ creation
- RFQ detail pages
- Pipeline board
- Quote form
- Deal status changes
- Inventory reservation requests

## Marketing User

Marketing User was originally tied to Jonah's module.

**Current note:** Jonah was removed from the group, so this role and module remain part of the requirements, but the implementation responsibility is currently unassigned.

Marketing User focuses on:

- Campaign list
- Campaign create/edit form
- Audience selection
- Campaign metrics
- Campaign dashboard card

## Inventory Manager

Inventory Manager focuses on:

- Product list
- Product detail
- Stock updates
- Reserved inventory
- Low-stock indicators
- RFQ-linked inventory reservations

---

# 4. Main Sitemap / Page List

```text
Login
└── Dashboard
    ├── Customer Management
    │   ├── Accounts List
    │   ├── Account Detail
    │   ├── Contacts
    │   ├── Contact Detail / Panel
    │   ├── Interaction History
    │   └── Import Accounts / Contacts
    │
    ├── RFQ / Pipeline Management
    │   ├── RFQ Pipeline Board
    │   ├── Create RFQ Form
    │   ├── RFQ Detail
    │   ├── Quote Form
    │   └── Deal Status: New / Review / Quoted / Negotiation / Won / Lost
    │
    ├── Digital Campaign Management
    │   ├── Campaigns List
    │   ├── Campaign Create/Edit Form
    │   ├── Audience Selection
    │   └── Campaign Metrics
    │
    ├── Inventory Management
    │   ├── Inventory List
    │   ├── Product Detail
    │   ├── Stock Update Form
    │   └── Inventory Reservation View
    │
    ├── Reports / Unified Dashboard
    │   ├── Customer Count
    │   ├── Active RFQs
    │   ├── Campaign Performance
    │   ├── Low Stock Products
    │   └── Reserved Inventory
    │
    └── Admin / Super Admin
        ├── Users
        ├── Roles
        └── Settings
```

---

# 5. Recommended Repo Structure

```text
typhon-cath-crm/
│
├── README.md
├── .gitignore
│
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   └── styles.css
│   │   ├── js/
│   │   │   ├── main.js
│   │   │   ├── customer.js
│   │   │   ├── rfq.js
│   │   │   ├── campaign.js
│   │   │   └── inventory.js
│   │   └── images/
│   │
│   └── admin/
│       ├── users.php
│       ├── roles.php
│       └── settings.php
│
├── config/
│   ├── database.php
│   └── app.php
│
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   ├── auth.php
│   ├── permissions.php
│   ├── validation.php
│   └── functions.php
│
├── modules/
│   ├── customer/
│   │   ├── accounts.php
│   │   ├── account_detail.php
│   │   ├── contacts.php
│   │   ├── interactions.php
│   │   └── customer_service.php
│   │
│   ├── rfq/
│   │   ├── pipeline.php
│   │   ├── create_rfq.php
│   │   ├── rfq_detail.php
│   │   ├── quotes.php
│   │   └── rfq_service.php
│   │
│   ├── campaign/
│   │   ├── campaigns.php
│   │   ├── campaign_form.php
│   │   ├── audience.php
│   │   ├── metrics.php
│   │   └── campaign_service.php
│   │
│   ├── inventory/
│   │   ├── products.php
│   │   ├── product_detail.php
│   │   ├── stock_update.php
│   │   ├── reservations.php
│   │   └── inventory_service.php
│   │
│   └── integration/
│       ├── dashboard_data.php
│       ├── reports.php
│       ├── api.php
│       └── shared_queries.php
│
├── sql/
│   ├── schema.sql
│   ├── seed.sql
│   └── indexes.sql
│
├── docs/
│   ├── diagrams/
│   ├── fp3_plan.md
│   ├── user_roles.md
│   └── final_report.md
│
└── tests/
    ├── customer_tests.md
    ├── rfq_tests.md
    ├── campaign_tests.md
    ├── inventory_tests.md
    └── integration_tests.md
```

---

# 6. FP3–FP10 Weekly Responsibility Matrix

| FP | Jonah / Marketing | Max | Casey | Trevor | All Active Members |
| --- | --- | --- | --- | --- | --- |
| FP3 | Campaign UI map — **removed from group; preserve for reassignment** | Customer UI map | Inventory UI map | RFQ UI map | Finalize sitemap, repo structure, role map, shared wireframe style |
| FP4 | Campaign schema/list page — **removed from group; preserve for reassignment** | Accounts/contacts schema and Accounts List page | Products/inventory schema and Inventory List page | RFQ/quotes schema and Pipeline Board draft | Repo setup, shared layout, database connection, login/session foundation |
| FP5 | Campaign create/edit form — **removed from group; preserve for reassignment** | Account/contact create/edit forms | Product create/edit and stock update forms | Create RFQ form and RFQ Detail page | Navbar/sidebar, Bootstrap styling, validation helpers |
| FP6 | Audience selection using tags/segments — **removed from group; preserve for reassignment** | Interaction history and account detail view | Reservation view and low-stock indicators | Quote form and RFQ stage updates | Role-based access checks and shared error handling |
| FP7 | Campaign/customer integration — **removed from group; preserve for reassignment** | Customer lookup for RFQs/campaigns | Inventory reservation integration with RFQs | RFQ customer lookup and inventory reservation requests | Shared service/helper functions and cross-module communication |
| FP8 | Campaign metrics dashboard card — **removed from group; preserve for reassignment** | Customer metrics dashboard cards | Inventory dashboard cards | RFQ pipeline dashboard cards | Unified dashboard and reports page |
| FP9 | Campaign testing/fixes — **removed from group; preserve for reassignment** | Customer CRUD and import/data-quality testing | Inventory stock/reservation edge-case testing | RFQ pipeline, quotes, won/lost flow testing | Full integration testing, seed data, permissions testing, documentation |
| FP10 | Final campaign demo/cleanup — **removed from group; preserve for reassignment** | Final customer demo/cleanup | Final inventory demo/cleanup | Final RFQ demo/cleanup | Final product demo, deployment, final report, slides/screenshots, backup plan |

---

# 7. FP Weekly To-Do Lists

## FP3 — Planning / Design

### Main goal

Create the planning package that shows what the CRM will look like, how it will be organized, and who owns each part.

### All Active Members

- [x] Finalize rough website outline / sitemap.
- [x] Finalize role-based UI map for Super Admin, Admin, Sales User, Marketing User, and Inventory Manager.
- [x] Finalize student/module ownership map.
- [x] Finalize repo structure.
- [x] Agree on shared Bootstrap wireframe style.
- [x] Create FP4–FP10 schedule.
- [x] Confirm how weekly progress will be pushed for review.

### Max — Customer Management

- [x] Draft Customer Management UI map.
- [x] Identify pages for Accounts List, Account Detail, Contacts, and Interaction History.
- [x] Define account/contact relationship rules.
- [x] Confirm account can exist without contacts.
- [x] Confirm contacts must belong to an account.

### Trevor — RFQ / Pipeline Management

- [x] Draft RFQ / Pipeline UI map.
- [x] Identify pages for Pipeline Board, Create RFQ, RFQ Detail, Quote Form, and Deal Status.
- [x] Define RFQ pipeline stages: New, In Review, Quoted, Negotiation, Won, Lost.
- [x] Confirm dashboard should include Open, Won, and Lost categories.

### Casey — Inventory Management

- [x] Draft Inventory Management UI map.
- [x] Identify pages for Inventory List, Product Detail, Stock Update Form, and Reservation View.
- [x] Define available quantity and reserved quantity needs.
- [x] Identify how inventory connects to RFQs.

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for Campaign UI map.
- [x] Originally responsible for Campaigns List, Campaign Form, Audience Selection, and Campaign Metrics.
- [x] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP3 Deliverables

- [x] Sitemap
- [x] Role-based UI map
- [x] Student/module ownership map
- [x] Repo structure
- [x] Initial wireframe plan
- [x] FP4–FP10 schedule

---

## FP4 — Foundation

### Main goal

Create the first working foundation of the application: repo, database connection, login/session skeleton, and starter pages for each module.

### All Active Members

- [x] Create GitHub repo or organize existing repo.
- [x] Add shared folder structure.
- [x] Add shared header/sidebar/footer includes.
- [x] Create database connection file.
- [x] Create basic login/session foundation.
- [x] Start shared CSS and Bootstrap layout.
- [x] Create initial SQL folder with schema draft.

### Max — Customer Management

- [x] Create accounts table/schema draft.
- [x] Create contacts table/schema draft.
- [x] Create interactions table/schema draft.
- [x] Build basic Accounts List page.
- [x] Show account fields: name/company, email, phone, address, industry, tags.

### Trevor — RFQ / Pipeline Management

- [x] Create RFQ table/schema draft.
- [x] Create quotes table/schema draft.
- [x] Create RFQ stage/status fields.
- [x] Build Pipeline Board draft.
- [x] Ensure RFQs can be linked to customers later.

### Casey — Inventory Management

- [x] Create products table/schema draft.
- [x] Create inventory/stock fields.
- [x] Include available quantity and reserved quantity.
- [x] Build basic Inventory List page.
- [x] Include fields for product name, SKU, price, and description.

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for campaign table/schema draft.
- [x] Originally responsible for basic Campaigns List page.
- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP4 Deliverables

- [x] Repo started
- [x] Shared PHP/MySQL connection
- [x] Login/session skeleton
- [x] Basic module list pages
- [x] Database schema draft started

---

## FP5 — Core CRUD

### Main goal

Build the first create/edit/read flows for each module.

### All Active Members

- [x] Apply shared navbar/sidebar layout across module pages.
- [x] Apply Bootstrap styling consistently.
- [x] Add shared validation helper functions.
- [x] Add basic error/success message display.
- [x] Make sure all pages connect to the shared database connection.

### Max — Customer Management

- [X] Build account create form.
- [X] Build account edit form.
- [X] Build contact create/edit forms.
- [X] Save accounts to MySQL.
- [X] Save contacts to MySQL.
- [X] Display saved accounts and contacts from MySQL.

### Trevor — RFQ / Pipeline Management

- [x] Build Create RFQ form.
- [x] Build RFQ Detail page.
- [x] Save RFQs to MySQL.
- [x] Display RFQs on Pipeline Board.
- [x] Start quote amount, discount, and validity-period fields.

### Casey — Inventory Management

- [x] Build product create/edit form.
- [x] Build stock update form.
- [x] Save products to MySQL.
- [x] Display product list from MySQL.
- [x] Track available and reserved quantities.

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for campaign create/edit form.
- [x] Originally responsible for saving campaigns to MySQL.
- [x] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP5 Deliverables

- [x] Create/edit forms for each active module
- [x] Basic validation
- [x] Database insert/update/read working
- [x] Shared Bootstrap layout applied

---

## FP6 — Module Workflows

### Main goal

Move beyond simple CRUD and build the actual business workflows inside each module.

### All Active Members

- [x] Add role-based access checks.
- [x] Add shared error handling.
- [x] Check that each module follows the shared layout and file structure.
- [x] Confirm each module can show useful business data, not just raw tables.

### Max — Customer Management

- [X] Build Account Detail view.
- [X] Build Interaction History view.
- [X] Allow calls, emails, notes, and meetings to be logged.
- [X] Show interactions under the correct account/contact.
- [X] Add customer search by name, email, and tags.
- [x] implement role checks 


### Trevor — RFQ / Pipeline Management

- [x] Build quote form.
- [x] Allow quote amount, discount, and validity period to be stored.
- [x] Add RFQ stage update controls.
- [x] Allow RFQs to move through New, In Review, Quoted, Negotiation, Won, and Lost.
- [x] Add Deal Won conversion behavior.
- [x] implement role checks 

### Casey — Inventory Management

- [x] Build inventory reservation view.
- [x] Add low-stock indicators.
- [x] Allow reserved quantity to be tracked separately from available quantity.
- [x] Prepare reservation logic for RFQ integration.
- [x] implement role checks 

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for campaign audience selection.
- [x] Originally responsible for using tags/segments/customer filters.
- [x] implement role checks 

- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP6 Deliverables

- [x] Customer interaction history
- [x] RFQ stage-change workflow
- [x] Quote workflow
- [x] Inventory reservation/stock workflow
- [x] Role checks
- [x] Shared error handling

---

## FP7 — Cross-Module Integration

### Main goal

Connect modules together so the CRM behaves like one system instead of separate pages.

### All Active Members

- [x] Build shared service/helper functions.
- [x] Add internal module communication patterns.
- [x] Confirm shared database relationships work across modules.
- [x] Test cross-module links manually.

### Max — Customer Management

- [x] Expose customer lookup for RFQ creation.
- [x] Expose customer tags/segments for campaign audience selection if the campaign module remains in scope.
- [x] Make account/contact data usable by other modules.

### Trevor — RFQ / Pipeline Management

- [x] Link RFQs to customers.
- [x] Connect RFQ creation to customer lookup.
- [x] Add inventory reservation request/attachment from RFQ detail.
- [x] Ensure RFQ won/lost status can affect reporting.

### Casey — Inventory Management

- [x] Integrate inventory reservations with RFQs.
- [x] Show RFQ-linked reservations.
- [x] Update reserved quantity when inventory is attached to an RFQ.
- [x] Prepare release/convert behavior depending on RFQ outcome.

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for integrating campaigns with customer data.
- [x] Originally responsible for campaign audience selection using customer segments/tags.
- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP7 Deliverables

- [x] RFQs linked to customers
- [x] RFQs linked to inventory reservations
- [x] Customer lookup available to other modules
- [x] Shared service/helper functions
- [x] Internal module communication working

---

## FP8 — Dashboard / Reporting

### Main goal

Build a unified dashboard and reports page that summarize the CRM.

### All Active Members

- [x] Build unified dashboard page.
- [ ] Build reports page.
- [x] Add dashboard cards for each module.
- [x] Make sure dashboard data comes from MySQL, not hardcoded values.
- [x] Include customer count, active RFQs, campaign performance if still in scope, inventory status, low-stock products, and reserved inventory.

### Max — Customer Management

- [x] Add customer count dashboard card.
- [x] Add recent interactions card/table.
- [ ] Add customer summary data for reports.

### Trevor — RFQ / Pipeline Management

- [x] Add RFQ stage/pipeline dashboard cards.
- [x] Add active RFQs count.
- [x] Add open/won/lost summary.
- [ ] Add quote/deal tracking summary if available.
- [ ] implement jquery tables to replace list views
- 1. excel, csv, pdf export
- 2. pagination - 10, 25, 50, 100, All
- 3. table searching
- 4. column filtering
- 5. column sorting

### Casey — Inventory Management

- [x] Add inventory status dashboard cards.
- [x] Add low-stock products card/table.
- [x] Add reserved inventory summary.
- [ ] Add ledger for each item in inventory tracking edits, creations, sales

### Jonah / Marketing — Removed from Group

- [x] Originally responsible for campaign metrics dashboard cards.
- [x] Originally responsible for sent count, open rate, and click rate metrics.
- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP8 Deliverables

- [x] Unified dashboard
- [ ] Reports page
- [x] Customer metrics
- [x] RFQ metrics
- [x] Inventory metrics
- [x] Campaign metrics if reassigned / still in scope
- [ ] implement pdf download for customer, inventory, rfq, campaigns for report like docs
- [ ] refactor dashboard

---

## FP9 — Testing / Polish

### Main goal

Test the full application, clean up UI issues, add seed data, and update documentation.

### All Active Members

- [ ] Perform full integration testing.
- [x] Add seed data.
- [ ] Test role permissions.
- [ ] Test navigation across all pages.
- [ ] Clean up UI inconsistencies.
- [ ] Update README and docs.
- [ ] Add known issues / limitations section.
- [x] Verify database indexes where needed.
- [x] Confirm passwords use bcrypt if authentication is implemented.
- [x] Confirm session management and role-based access control are documented.

### Max — Customer Management

- [ ] Test customer CRUD.
- [ ] Test contact CRUD.
- [ ] Test account/contact relationship rules.
- [ ] Test interaction history.
- [ ] Test customer search.
- [ ] Clean up customer UI.

### Trevor — RFQ / Pipeline Management

- [ ] Test RFQ creation.
- [ ] Test pipeline stage changes.
- [ ] Test quote creation/editing.
- [ ] Test won/lost flows.
- [ ] Test RFQ/customer linkage.
- [ ] Test RFQ/inventory reservation linkage.
- [ ] Clean up RFQ UI.

### Casey — Inventory Management

- [ ] Test product CRUD.
- [ ] Test stock updates.
- [ ] Test reserved quantity behavior.
- [ ] Test low-stock indicators.
- [ ] Test inventory reservation edge cases.
- [ ] Clean up inventory UI.

### Jonah / Marketing — Removed from Group

- [ ] Originally responsible for testing campaign flows and fixing campaign UI issues.
- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP9 Deliverables

- [ ] Full CRUD testing
- [ ] Role testing
- [ ] Integration testing
- [x] Seed data
- [ ] UI cleanup
- [x] Error handling
- [ ] Documentation updates

---

## FP10 — Final Product

### Main goal

Prepare the final working CRM, demo, documentation, screenshots, and backup plan.

### All Active Members

- [ ] Prepare final product demo.
- [ ] Prepare final deployment or working local setup.
- [ ] Prepare final report.
- [ ] Add final diagrams/screenshots.
- [ ] Add final backup/export plan.
- [ ] Add known limitations section.
- [ ] Confirm README setup instructions are complete.
- [ ] Confirm database schema and seed instructions are complete.
- [ ] Confirm each active member can explain their module.

### Max — Customer Management

- [ ] Finalize customer module demo.
- [ ] Finalize customer module cleanup.
- [ ] Document account/contact rules.
- [ ] Add screenshots of customer pages.

### Trevor — RFQ / Pipeline Management

- [ ] Finalize RFQ module demo.
- [ ] Finalize RFQ module cleanup.
- [ ] Document RFQ pipeline stages and quote tracking.
- [ ] Add screenshots of RFQ pages.

### Casey — Inventory Management

- [ ] Finalize inventory module demo.
- [ ] Finalize inventory module cleanup.
- [ ] Document stock and reservation logic.
- [ ] Add screenshots of inventory pages.

### Jonah / Marketing — Removed from Group

- [ ] Originally responsible for final campaign demo and cleanup.
- [ ] **Note:** Jonah was removed from the group. These tasks must be reassigned or discussed with the instructor.

### FP10 Deliverables

- [ ] Final deployed or working CRM
- [ ] Final demo
- [ ] Final documentation
- [ ] Final diagrams/screenshots
- [ ] Final report
- [ ] Backup/export plan
- [ ] Known limitations section

---

# 8. Simpler X Responsibility Matrix

Because Jonah was removed from the group, his X entries are marked as historical/unassigned.

| FP | Jonah / Marketing | Max | Casey | Trevor | All Active Members |
| --- | --- | --- | --- | --- | --- |
| FP3 | Historical / Unassigned | X | X | X | X |
| FP4 | Historical / Unassigned | X | X | X | X |
| FP5 | Historical / Unassigned | X | X | X | X |
| FP6 | Historical / Unassigned | X | X | X | X |
| FP7 | Historical / Unassigned | X | X | X | X |
| FP8 | Historical / Unassigned | X | X | X | X |
| FP9 | Historical / Unassigned | X | X | X | X |
| FP10 | Historical / Unassigned | X | X | X | X |

---

# 9. Cross-Module Feature Additions (Integration Layer)

These are cross-cutting features built by the active members as shared
infrastructure. They are not owned by a single module — every active module
consumes them — so they are tracked here rather than under one person's weekly
list.

## Pagination (All Active Members)

- [x] Build framework-agnostic `Paginator` core class (`app/Core/Paginator.php`).
- [x] Whitelist selectable page sizes (25 / 50 / 100) with a configurable default.
- [x] Support a "show all" option to render every row on one page.
- [x] Clamp out-of-range page requests (e.g. `?page=9999` lands on the last page).
- [x] Expose `from()` / `to()` row indices and windowed page numbers with `…` gap markers.
- [x] Build shared pagination nav partial (`app/Shared/pagination.php`) — «Prev · 1 … 4 5 6 … 12 · Next».
- [x] Preserve current query string (search, filters, sort, per_page) across page clicks.
- [x] Build shared per-page `<select>` partial (`app/Shared/per_page_select.php`).
- [x] Wire pagination into Customer (Accounts List).
- [x] Wire pagination into RFQ (Pipeline Board, Win Rate).
- [x] Wire pagination into Inventory (Products List).
- [x] Wire pagination into Campaign (Campaigns List).
- [x] Wire pagination into Admin (Users).

## CSRF Protection (All Active Members)

- [x] Build `Csrf` core class (`app/Core/Csrf.php`) with per-session token generation.
- [x] Provide `Csrf::field()` hidden-input helper and `Csrf::metaTag()` for AJAX.
- [x] Validate submitted tokens in constant time via `hash_equals` (POST body + `X-CSRF-Token` header).
- [x] Build enforcement middleware (`app/Middleware/csrf.php`) — safe methods pass, invalid/missing token returns 403.
- [x] Embed `Csrf::field()` in all POST forms across Customer, RFQ, Inventory, Campaign, and Admin.
- [x] Include the CSRF middleware in all POST handlers app-wide (login, admin users/permissions, and every module).
- [x] Add CSRF coverage test harness (`tests/csrf_coverage.php`) to verify token generation and form submission.

---

# 10. Final Submission Checklist

Use this checklist before uploading or submitting each FP package.

- [ ] Does this week's submission match the FP deliverables?
- [ ] Does each active member have visible progress?
- [ ] Are Jonah's old tasks either reassigned, documented as unassigned, or discussed with the instructor?
- [ ] Are screenshots included if the FP asks for UI progress?
- [ ] Is the repo structure clean and understandable?
- [ ] Is the database schema updated if new tables were added?
- [ ] Are source files committed to GitHub?
- [ ] Is the README updated with setup or usage notes?
- [ ] Are known bugs or limitations documented honestly?
- [ ] Can the instructor/client understand what changed this week?

---

# 11. Notes for Instructor / Reviewer

This weekly plan is based on the original four-student project structure. Jonah was originally responsible for Digital Campaign Management, but he has been removed from the group. His section remains visible so the removed work does not disappear from the planning record.

The remaining active members should either redistribute the Digital Campaign Management work or clarify with the instructor whether that module should be reduced in scope.
