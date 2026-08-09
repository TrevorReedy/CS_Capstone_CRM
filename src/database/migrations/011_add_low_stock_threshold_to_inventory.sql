
ALTER TABLE inventory
    ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 10 AFTER reserved_quantity;