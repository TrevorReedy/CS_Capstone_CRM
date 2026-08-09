-- schema.sql — tables, primary keys, foreign keys and uniques.
--
-- Secondary and FULLTEXT indexes live in indexes.sql, which must be applied
-- after this file (docker-compose mounts it as 03-indexes.sql).
--
-- No CREATE DATABASE / USE here on purpose: the MySQL entrypoint already
-- selects MYSQL_DATABASE, and db_setup.php connects to the database named in
-- config/database.php. Hardcoding `typhon_cath_crm` made DB_NAME a lie —
-- the schema always landed in that database no matter what was configured.

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    owner_user_id INT NULL  -- non-null = custom role scoped to one user; FK added after users (see bottom of file)
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(255) NOT NULL,
    parent_account_id INT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    industry VARCHAR(150),
    source VARCHAR(150),
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_account_id) REFERENCES accounts(id)
);

CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    first_name VARCHAR(150) NOT NULL,
    last_name VARCHAR(150) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    title VARCHAR(150),
    source VARCHAR(150),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

CREATE TABLE interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    contact_id INT NULL,
    user_id INT NOT NULL,
    interaction_type ENUM('call', 'email', 'note', 'meeting') NOT NULL,
    interaction_date DATETIME NOT NULL,
    interaction_subject TEXT NOT NULL,
    notes TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- CASCADE on the account: interaction history has no meaning without the
    -- account it belongs to, and letting the DB do it removes the hand-rolled
    -- "delete interactions, then delete account" sequence in account_detail.php
    -- that could lose history if the second statement failed.
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    -- SET NULL on the contact: the conversation still happened even if the
    -- person record is gone.
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE rfqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NULL,
    contact_id INT NULL,
    created_by_user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    stage ENUM('New', 'In Review', 'Quoted', 'Negotiation', 'Won', 'Lost') DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    -- SET NULL, not CASCADE: contact_id is already nullable and is only the
    -- "who we spoke to" pointer. Cascading meant deleting one contact silently
    -- destroyed every RFQ naming them, plus their quotes and reservations.
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_id INT NOT NULL,
    quote_amount DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0,
    validity_start_date DATE,
    validity_end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL UNIQUE,
    available_quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE rfq_inventory_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_reserved INT NOT NULL,
    reservation_status ENUM('Reserved', 'Released', 'Converted') DEFAULT 'Reserved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Append-only audit ledger of inventory-affecting events (see migrations/015_create_inventory_movements.sql).
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

CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(255) NOT NULL,
    campaign_type ENUM('Email', 'SMS Simulation') NOT NULL DEFAULT 'Email',
    status ENUM('Draft', 'Scheduled', 'Sent', 'Completed') DEFAULT 'Draft',
    scheduled_at DATETIME NULL,
    created_by_user_id INT NOT NULL,
    sent_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE campaign_audience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    account_id INT NULL,
    contact_id INT NULL,
    tag_filter VARCHAR(255),
    segment_name VARCHAR(255),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    -- CASCADE: an audience row is nothing but "this account/contact was targeted".
    -- Left restrictive, these FKs made customer deletion fail outright with a raw
    -- FK error the moment the customer had ever been in a campaign audience.
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);

CREATE TABLE role_permissions (
    role_id    INT          NOT NULL,
    permission VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE audience_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    preset_name VARCHAR(255) NOT NULL,
    segment_name VARCHAR(255) NOT NULL,
    tag_filter VARCHAR(255) NULL,
    account_ids TEXT NULL,
    contact_ids TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);


-- ─────────────────────────────────────────────────────────────────────────────
-- Deferred constraints
--
-- Declared here rather than inline because they either close a cycle (roles ↔
-- users) or express a domain rule that no column type can enforce on its own.
-- ─────────────────────────────────────────────────────────────────────────────

-- roles.owner_user_id had no FK at all, so deleting a user left orphaned custom
-- roles pointing at a vanished owner. Cannot be inline: roles is created before
-- users, and users references roles.
ALTER TABLE roles
    ADD CONSTRAINT fk_roles_owner_user
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Domain invariants. The application validates these too, but the app is not the
-- only writer (seed scripts, db_setup.php, manual SQL, Adminer), and a race
-- between check-then-write can still land an invalid row. MySQL 8.0.16+ enforces
-- CHECK constraints for real.
ALTER TABLE products
    ADD CONSTRAINT chk_products_price CHECK (price >= 0);

ALTER TABLE inventory
    ADD CONSTRAINT chk_inventory_available CHECK (available_quantity >= 0),
    ADD CONSTRAINT chk_inventory_reserved  CHECK (reserved_quantity  >= 0),
    ADD CONSTRAINT chk_inventory_threshold CHECK (low_stock_threshold >= 0);

ALTER TABLE quotes
    ADD CONSTRAINT chk_quotes_amount   CHECK (quote_amount >= 0),
    ADD CONSTRAINT chk_quotes_discount CHECK (discount >= 0 AND discount <= quote_amount),
    ADD CONSTRAINT chk_quotes_dates    CHECK (
        validity_start_date IS NULL
        OR validity_end_date IS NULL
        OR validity_end_date >= validity_start_date
    );

ALTER TABLE campaigns
    ADD CONSTRAINT chk_campaigns_sent_count CHECK (sent_count >= 0);

-- A reservation of zero or fewer units is meaningless, and the same product
-- should appear at most once per RFQ (edit the quantity instead of stacking
-- rows, which made the reserved totals impossible to reconcile).
ALTER TABLE rfq_inventory_reservations
    ADD CONSTRAINT chk_reservations_quantity CHECK (quantity_reserved > 0),
    ADD CONSTRAINT uq_reservations_rfq_product UNIQUE (rfq_id, product_id);
