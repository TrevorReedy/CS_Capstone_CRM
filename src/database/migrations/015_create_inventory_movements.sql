-- 015_create_inventory_movements.sql
-- Append-only ledger of inventory-affecting events triggered from the
-- Inventory module: product creation, attribute edits, manual stock
-- adjustments, deletions, and reservation release/convert actions taken from
-- the Inventory module's own Reservations page. Answers "what happened to
-- this product, when, and who did it" for the printable Inventory Ledger
-- report (InventoryController::ledger()).
--
-- Scope note: reservation create/release/convert actions taken from the RFQ
-- module are not logged here — that module is owned by another team and was
-- intentionally left untouched, so RFQ-driven stock changes won't appear in
-- this ledger unless/until that module's write paths call
-- InventoryRepository::logMovement() too.
--
-- product_id/user_id use ON DELETE SET NULL rather than CASCADE: a ledger
-- row must survive the product being deleted or the user account being
-- removed later, so product_name/sku and user_name are snapshotted onto the
-- row at write time instead of being resolved via JOIN at read time.

CREATE TABLE inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    user_id INT NULL,
    user_name VARCHAR(150) NULL,
    movement_type ENUM('created', 'updated', 'manual_adjustment', 'reserved', 'released', 'converted', 'deleted') NOT NULL,
    quantity_delta INT NULL,
    available_quantity_after INT NULL,
    reserved_quantity_after INT NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- InventoryRepository::movements()/movementsCount(): filter by product, sort by date.
CREATE INDEX idx_inventory_movements_product_id ON inventory_movements(product_id);
CREATE INDEX idx_inventory_movements_created_at ON inventory_movements(created_at);
CREATE INDEX idx_inventory_movements_user_id ON inventory_movements(user_id);
CREATE INDEX idx_inventory_movements_movement_type ON inventory_movements(movement_type);
