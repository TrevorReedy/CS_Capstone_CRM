app/Modules/RFQ/

The RFQ module owns:

RFQ creation and tracking
sales pipeline stages
quote and deal tracking

Recommended structure:

RFQ/
├── RFQController.php
├── RFQService.php
├── RFQRepository.php
├── rfq_routes.php
└── views/
    ├── pipeline_board.php
    ├── create_rfq.php
    ├── rfq_detail.php
    └── quote_form.php
RFQ pages
RFQ Pipeline Board
Create RFQ Form
RFQ Detail
Quote Form
Deal Tracking
RFQ database ownership

Trevor’s module mainly works with:

rfqs
quotes
pipeline stages
rfq_inventory_reservations

The RFQ module also reads from:

accounts
contacts
products
inventory

But it does not fully own those tables.

RFQ module flow

Example: User creates an RFQ.

create_rfq.php
        ↓
require_auth.php checks login
        ↓
Permissions::require('rfqs.create')
        ↓
RFQController receives form
        ↓
Validator checks required fields
        ↓
RFQService applies RFQ rules
        ↓
RFQRepository inserts RFQ into MySQL
        ↓
View redirects to rfq_detail.php