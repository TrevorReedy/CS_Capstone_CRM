-- 020_integrity_constraints.sql
--
-- Upgrade path for databases that already exist. schema.sql now declares all of
-- this for fresh installs; this file brings a live database to the same state.
--
-- Run order matters: the data fixes come before the constraints, because MySQL
-- validates a CHECK against existing rows when you add it. If any statement
-- fails with "Check constraint is violated", there is real bad data in that
-- table — find it with the SELECT in the comment above the constraint, fix it,
-- then re-run.
--
-- Safe to run once. MySQL has no "ADD CONSTRAINT IF NOT EXISTS", so re-running
-- reports duplicate-name errors; those are harmless and mean it already applied.

-- ── 1. Foreign key policy ───────────────────────────────────────────────────

-- rfqs.contact_id was ON DELETE CASCADE: deleting one contact destroyed every
-- RFQ that named them, along with its quotes and reservations. The FK name is
-- auto-generated, so look it up first:
--   SELECT constraint_name FROM information_schema.key_column_usage
--    WHERE table_schema = DATABASE() AND table_name = 'rfqs' AND column_name = 'contact_id';
-- then substitute it below (it is normally rfqs_ibfk_2).
ALTER TABLE rfqs DROP FOREIGN KEY rfqs_ibfk_2;
ALTER TABLE rfqs
    ADD CONSTRAINT fk_rfqs_contact
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

-- campaign_audience blocked customer deletion entirely: a restrictive FK meant
-- any account or contact that had ever been in an audience could never be
-- deleted. Audience rows are pure membership, so they should follow the target.
ALTER TABLE campaign_audience DROP FOREIGN KEY campaign_audience_ibfk_2;
ALTER TABLE campaign_audience DROP FOREIGN KEY campaign_audience_ibfk_3;
ALTER TABLE campaign_audience
    ADD CONSTRAINT fk_campaign_audience_account
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_campaign_audience_contact
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE;

-- interactions: cascade from the account (removes the hand-rolled
-- delete-interactions-then-delete-account sequence that could lose history),
-- null out the contact pointer (the conversation still happened).
ALTER TABLE interactions DROP FOREIGN KEY interactions_ibfk_1;
ALTER TABLE interactions DROP FOREIGN KEY interactions_ibfk_2;
ALTER TABLE interactions
    ADD CONSTRAINT fk_interactions_account
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_interactions_contact
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL;

-- roles.owner_user_id had no foreign key at all, so deleting a user left
-- orphaned custom roles pointing at nothing.
-- Clear any orphans first, or the FK will not build:
UPDATE roles SET owner_user_id = NULL
 WHERE owner_user_id IS NOT NULL
   AND owner_user_id NOT IN (SELECT id FROM users);
ALTER TABLE roles
    ADD CONSTRAINT fk_roles_owner_user
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE;

-- ── 2. Data repair before the CHECK constraints ─────────────────────────────
-- Nothing enforced these before, so a live database may already violate them.

UPDATE products  SET price = 0 WHERE price < 0;
UPDATE inventory SET available_quantity  = 0 WHERE available_quantity  < 0;
UPDATE inventory SET reserved_quantity   = 0 WHERE reserved_quantity   < 0;
UPDATE inventory SET low_stock_threshold = 0 WHERE low_stock_threshold < 0;
UPDATE quotes    SET quote_amount = 0 WHERE quote_amount < 0;
UPDATE quotes    SET discount = 0 WHERE discount < 0;
-- A discount above the quote amount is a data-entry error; clamp it.
UPDATE quotes    SET discount = quote_amount WHERE discount > quote_amount;
-- An end date before the start date cannot be repaired automatically — drop the
-- end date and let someone re-enter it.
UPDATE quotes    SET validity_end_date = NULL
 WHERE validity_start_date IS NOT NULL
   AND validity_end_date   IS NOT NULL
   AND validity_end_date < validity_start_date;
UPDATE campaigns SET sent_count = 0 WHERE sent_count < 0;
DELETE FROM rfq_inventory_reservations WHERE quantity_reserved <= 0;

-- Collapse duplicate (rfq_id, product_id) reservations into one row before the
-- unique key is added. Keeps the lowest id, sums the quantities onto it.
UPDATE rfq_inventory_reservations r
  JOIN (
        SELECT MIN(id) AS keep_id, rfq_id, product_id, SUM(quantity_reserved) AS total
          FROM rfq_inventory_reservations
         GROUP BY rfq_id, product_id
        HAVING COUNT(*) > 1
  ) d ON r.id = d.keep_id
   SET r.quantity_reserved = d.total;

DELETE r FROM rfq_inventory_reservations r
  JOIN (
        SELECT MIN(id) AS keep_id, rfq_id, product_id
          FROM rfq_inventory_reservations
         GROUP BY rfq_id, product_id
        HAVING COUNT(*) > 1
  ) d ON r.rfq_id = d.rfq_id AND r.product_id = d.product_id
 WHERE r.id <> d.keep_id;

-- ── 3. Domain constraints ───────────────────────────────────────────────────

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

ALTER TABLE rfq_inventory_reservations
    ADD CONSTRAINT chk_reservations_quantity   CHECK (quantity_reserved > 0),
    ADD CONSTRAINT uq_reservations_rfq_product UNIQUE (rfq_id, product_id);
