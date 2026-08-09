app/Modules/Inventory/

The Inventory module owns:

product catalog
stock tracking
inventory allocation to RFQs

Recommended structure:

Inventory/
├── InventoryController.php
├── InventoryService.php
├── InventoryRepository.php
├── inventory_routes.php
└── views/
    ├── products_list.php
    ├── product_detail.php
    ├── stock_update.php
    └── reservations.php
Inventory pages
Inventory List
Product Detail
Stock Update Form
Inventory Reservation View
Low Stock View
Inventory database ownership

Casey’s module mainly works with:

products
inventory
rfq_inventory_reservations

The Inventory module also reads from:

rfqs
quotes
accounts

because reservations are connected to RFQs.

Inventory module flow

Example: User updates stock.

stock_update.php
        ↓
require_auth.php checks login
        ↓
Permissions::require('inventory.update_stock')
        ↓
InventoryController receives form
        ↓
Validator checks quantity
        ↓
InventoryService checks stock rules
        ↓
InventoryRepository updates inventory table
        ↓
View redirects to product_detail.php