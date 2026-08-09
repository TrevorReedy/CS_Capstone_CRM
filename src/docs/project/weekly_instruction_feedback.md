FP3:  1. Rough outline of the website (pages/actions/sitemap)
         Visualization of how the user interaface for the CRM site going to look like! (Super Admin, Admin, other roles) (For each role, how is the UI going to look like)
       2. Code structure of the repo
       3. Need weekly plan for the rest of the iterations as follows. (assume 4 folks for now)

             Jonah     Max     Casey        Trevor   All

FP3            X        X        X            X
FP4
FP5
..

FP10


## FP3 — CRM Website Outline / UI Map / Repo Plan

This FP3 package should explain three things:


---

# 1. Rough Website Outline / Sitemap

## Main sitemap

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

The uploaded navigation map already lists these core screens: Login, Dashboard, Accounts, RFQ Pipeline, Campaigns, Inventory, Reports, Admin Users/Roles, and Settings. 

---

# 2. UI Map by Role and Student Owner

## Super Admin UI — Owned by **All**

| Area            | What the UI shows                              | Main actions              | Student owner |
| --------------- | ---------------------------------------------- | ------------------------- | ------------- |
| Dashboard       | Full system overview                           | View all module metrics   | All           |
| User Management | User list, roles, permissions                  | Add/edit/deactivate users | All           |
| Role Settings   | Admin, Sales, Marketing, Inventory permissions | Update access control     | All           |
| System Settings | Global settings/configuration                  | Edit app-level settings   | All           |
| Reports         | Unified reporting across all modules           | View/export reports       | All           |

**Visual layout:**
Super Admin gets the full sidebar:

```text
Dashboard
Customers
RFQs / Pipeline
Campaigns
Inventory
Reports
Admin
Settings
```

Super Admin should be able to see every module because the permission matrix says Admin has full access, user management, and system configuration. 

---

## Admin UI — Owned by **All**

| Area      | What the UI shows             | Main actions                    | Student owner |
| --------- | ----------------------------- | ------------------------------- | ------------- |
| Dashboard | CRM summary cards             | View totals and recent activity | All           |
| Customers | Accounts and contacts         | View/edit customer records      | Max           |
| RFQs      | Pipeline board and quotes     | View/edit RFQs and deals        | Trevor        |
| Campaigns | Campaign status and metrics   | View/edit campaigns             | Jonah         |
| Inventory | Products, stock, reservations | View/edit inventory             | Casey         |
| Reports   | Cross-module summaries        | View reports                    | All           |

**Visual layout:**

```text
Top Nav: Typhon Cath CRM | Search | User Menu

Sidebar:
- Dashboard
- Customers
- RFQ Pipeline
- Campaigns
- Inventory
- Reports
```

Admin is basically the “business manager” role. They can work across modules but do not need to focus on user/role configuration as much as Super Admin.

---

## Sales User UI — Owned mainly by **Trevor**, with **Max** dependency

| Area                | What the UI shows                             | Main actions                              | Student owner  |
| ------------------- | --------------------------------------------- | ----------------------------------------- | -------------- |
| Dashboard           | Active RFQs, pipeline value, recent customers | View sales summary                        | Trevor / All   |
| Accounts            | Customer/account lookup                       | View customers before RFQ creation        | Max            |
| Create RFQ          | RFQ form linked to account/contact            | Create RFQ                                | Trevor         |
| Pipeline Board      | RFQs grouped by stage                         | Move RFQs through stages                  | Trevor         |
| RFQ Detail          | RFQ info, quote info, reserved inventory      | Edit RFQ, update stage, request inventory | Trevor         |
| Quote Form          | Amount, discount, validity period             | Create/update quote                       | Trevor         |
| Reservation Request | Products needed for RFQ                       | Request/attach inventory                  | Trevor + Casey |

Sales User should focus on the RFQ workflow. The uploaded permission matrix gives Sales User access to customer records, RFQ creation, stage updates, quotes, and RFQ win/loss status. 

---

## Customer Management UI — Owned by **Max**

| Area                | What the UI shows                             | Main actions                | Student owner |
| ------------------- | --------------------------------------------- | --------------------------- | ------------- |
| Accounts List       | Table of companies/accounts                   | Search, filter, add account | Max           |
| Account Detail      | Account profile, contacts, interactions, RFQs | Edit account                | Max           |
| Contacts Panel      | Contacts under an account                     | Add/edit/delete contacts    | Max           |
| Interaction History | Calls, emails, meetings, notes                | Add interaction log         | Max           |
| Import Accounts     | Upload/import customer data                   | Import records              | Max           |

**Visual layout:**

```text
Customers Page
-------------------------------------------------
Search bar | Add Account | Import
-------------------------------------------------
Accounts table
- Account Name
- Industry
- Email
- Phone
- Tags
- Last Interaction
- Actions
-------------------------------------------------
Right/detail panel or Account Detail page
```

Max owns the customer profile, contact, and interaction history pieces. The CRUD matrix lists Accounts, Contacts, and Interactions as full Customer Module responsibilities. 

---

## Marketing User UI — Owned by **Jonah**

| Area               | What the UI shows                 | Main actions             | Student owner          |
| ------------------ | --------------------------------- | ------------------------ | ---------------------- |
| Campaigns List     | Campaign table/status             | View campaigns           | Jonah                  |
| Campaign Form      | Name, type, status, content/notes | Create/edit campaign     | Jonah                  |
| Audience Selection | Tags, segments, customer filters  | Choose campaign audience | Jonah + Max dependency |
| Campaign Metrics   | Sent count, open rate, click rate | View/update metrics      | Jonah                  |
| Dashboard Card     | Campaign performance summary      | View campaign results    | Jonah / All            |

**Visual layout:**

```text
Campaigns Page
-------------------------------------------------
Create Campaign | Filter by Status
-------------------------------------------------
Campaign cards/table
- Campaign Name
- Type
- Status
- Audience Count
- Sent Count
- Open Rate
- Click Rate
-------------------------------------------------
Campaign Detail:
- Campaign Info
- Audience Criteria
- Metrics
```

The campaign flow depends on customer data because campaigns select audiences using segments, tags, or filters. 

---

## Inventory Manager UI — Owned by **Casey**

| Area                 | What the UI shows              | Main actions                         | Student owner             |
| -------------------- | ------------------------------ | ------------------------------------ | ------------------------- |
| Inventory List       | Products and quantities        | View/search inventory                | Casey                     |
| Product Detail       | SKU, price, description, stock | Edit product                         | Casey                     |
| Stock Update Form    | Available/reserved quantity    | Update stock                         | Casey                     |
| RFQ Reservation View | RFQ-linked reservations        | Approve/release/convert reservations | Casey + Trevor dependency |
| Low Stock Dashboard  | Low inventory alerts           | View inventory warnings              | Casey / All               |

**Visual layout:**

```text
Inventory Page
-------------------------------------------------
Search Products | Add Product | Low Stock Filter
-------------------------------------------------
Product table
- SKU
- Product Name
- Price
- Available Qty
- Reserved Qty
- Status
- Actions
-------------------------------------------------
Reservation Panel:
- RFQ #
- Product
- Quantity Reserved
- Reservation Status
```

Inventory connects directly to RFQs because RFQs can reserve products, update reserved quantity, and release/convert reservations depending on whether the RFQ is won or lost. 

---

# 3. Code Structure of the Repo

Assuming the required stack stays as **HTML/CSS/JavaScript/jQuery/Bootstrap frontend, PHP backend, and MySQL database**, the repo should look like this:

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

The uploaded architecture notes say all modules should share PHP services/includes and a shared MySQL database, so each module should have its own folder but still use shared config, auth, permissions, and database logic. 

---

# 4. Student Ownership Map

| Student    | Module                      | Main Role UI                              | Main responsibility                                      |
| ---------- | --------------------------- | ----------------------------------------- | -------------------------------------------------------- |
| **Max**    | Customer Management         | Customer Manager / Sales customer screens | Accounts, contacts, interaction history                  |
| **Trevor** | RFQ / Pipeline Management   | Sales User UI                             | RFQ creation, pipeline stages, quotes, deal tracking     |
| **Jonah**  | Digital Campaign Management | Marketing User UI                         | Campaign creation, segmentation, performance metrics     |
| **Casey**  | Inventory Management        | Inventory Manager UI                      | Products, stock, inventory allocation to RFQs            |
| **All**    | Integration Layer           | Super Admin / Admin / Dashboard           | Shared database, dashboard, reports, RBAC, internal APIs |

---

# 5. FP3–FP10 Weekly Plan

| FP       | Jonah                                                            | Max                                                    | Casey                                                    | Trevor                                                             | All                                                                           |
| -------- | ---------------------------------------------------------------- | ------------------------------------------------------ | -------------------------------------------------------- | ------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| **FP3**  | Campaign UI map                                                  | Customer UI map                                        | Inventory UI map                                         | RFQ UI map                                                         | Finalize sitemap, repo structure, role map, shared wireframe style            |
| **FP4**  | Create campaign table/schema draft and basic Campaigns List page | Create accounts/contacts schema and Accounts List page | Create products/inventory schema and Inventory List page | Create RFQ/quotes schema and Pipeline Board draft                  | Create repo, shared layout, database connection, login/session foundation     |
| **FP5**  | Build campaign create/edit form                                  | Build account/contact create/edit forms                | Build product create/edit and stock update forms         | Build Create RFQ form and RFQ Detail page                          | Connect shared navbar/sidebar, Bootstrap styling, validation helpers          |
| **FP6**  | Build audience selection using tags/segments                     | Build interaction history and account detail view      | Build reservation view and low-stock indicators          | Build quote form and RFQ stage updates                             | Add role-based access checks and shared error handling                        |
| **FP7**  | Integrate campaigns with customer data for audience selection    | Expose customer lookup for RFQs/campaigns              | Integrate inventory reservation with RFQs                | Connect RFQs to customer lookup and inventory reservation requests | Build internal module communication/shared service functions                  |
| **FP8**  | Campaign metrics dashboard cards                                 | Customer count/recent interactions dashboard cards     | Inventory status/low stock dashboard cards               | RFQ stage/pipeline dashboard cards                                 | Build unified dashboard and reports page                                      |
| **FP9**  | Test campaign flows and fix UI issues                            | Test customer CRUD and import/data quality             | Test inventory stock/reservation edge cases              | Test RFQ pipeline, quotes, won/lost flows                          | Full integration testing, seed data, permissions testing, documentation       |
| **FP10** | Final campaign demo and cleanup                                  | Final customer demo and cleanup                        | Final inventory demo and cleanup                         | Final RFQ demo and cleanup                                         | Final product demo, deployment, final report, slides/screenshots, backup plan |

---

# 6. Simpler X Responsibility Matrix

| FP       | Jonah | Max | Casey | Trevor | All |
| -------- | ----: | --: | ----: | -----: | --: |
| **FP3**  |     X |   X |     X |      X |   X |
| **FP4**  |     X |   X |     X |      X |   X |
| **FP5**  |     X |   X |     X |      X |   X |
| **FP6**  |     X |   X |     X |      X |   X |
| **FP7**  |     X |   X |     X |      X |   X |
| **FP8**  |     X |   X |     X |      X |   X |
| **FP9**  |     X |   X |     X |      X |   X |
| **FP10** |     X |   X |     X |      X |   X |

---

# 7. What Each FP Should Produce

## FP3 — Planning / Design

Deliverables:

* Sitemap
* Role-based UI map
* Student/module ownership map
* Repo structure
* Initial wireframe plan
* FP4–FP10 schedule

## FP4 — Foundation

Deliverables:

* Database schema started **Max**
* Shared PHP/MySQL connection **Trevor**
* Login/session skeleton **Casey**
* Basic list page for each module **Jonah**

## FP5 — Core CRUD

Deliverables:
**Everyone has their own pages**
* Create/edit forms for each module **Everyone**
* Basic validation **Trevor**
* Database insert/update/read working **Max**
* Shared Bootstrap layout applied **Jonah**

## FP6 — Module Workflows

Deliverables:

* Customer interactions **Max**
* RFQ stage changes **Trevor**
* Campaign audience selection **Jonah**
* Inventory reservation/stock logic **Casey**
* Role checks added **Trevor**

## FP7 — Cross-Module Integration

Deliverables:

* RFQs linked to customers **Trevor**
* Campaigns linked to customer segments/tags **Jonah**
* RFQs linked to inventory reservations **Trevor**
* Shared service/helper functions **Max**

## FP8 — Dashboard / Reporting

Deliverables:

* Unified dashboard
* Customer metrics
* RFQ metrics
* Campaign metrics
* Inventory metrics
* Reports page

The dashboard should combine customer counts, active RFQs, campaign performance, and inventory status according to the uploaded dashboard data-flow plan. 

## FP9 — Testing / Polish

Deliverables:

* Full CRUD testing
* Role testing
* Integration testing
* Seed data
* UI cleanup
* Error handling
* Documentation updates

## FP10 — Final Product

Deliverables:

* Final deployed/working CRM
* Final demo
* Final documentation
* Final diagrams/screenshots
* Final report
* Backup/export plan
* Known limitations section



