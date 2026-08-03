-- indexes.sql — canonical index set for a fresh install.
--
-- schema.sql creates tables, PKs, FKs and uniques only. Every secondary and
-- FULLTEXT index lives here, and docker-compose mounts this as 03-indexes.sql
-- so `docker compose up` on an empty volume produces a fully indexed database.
-- Before this file was wired into the entrypoint a fresh install had no
-- FULLTEXT indexes at all, and RFQ/account/campaign search failed outright with
-- "Can't find FULLTEXT index matching the column list".
--
-- This is the union of what used to be split across migrations 013–018. Those
-- migrations remain the upgrade path for databases that already exist; this file
-- is the current-state definition for new ones. Keep the two in sync.
--
-- No USE statement: the MySQL entrypoint already selects MYSQL_DATABASE, so
-- hardcoding a database name here would break any deployment that renames it.

-- ── FULLTEXT search ─────────────────────────────────────────────────────────
-- Required by RFQRepository::buildWhere() and ServerTable's 'fulltext' mode.
-- Terms below the InnoDB minimum token size fall back to LIKE, so a missing
-- index only breaks the longer queries — which is most of them.
ALTER TABLE rfqs      ADD FULLTEXT INDEX ft_rfqs_title     (title);
ALTER TABLE accounts  ADD FULLTEXT INDEX ft_accounts_name  (account_name);
ALTER TABLE campaigns ADD FULLTEXT INDEX ft_campaigns_name (campaign_name);

-- ── Foreign keys and joins ──────────────────────────────────────────────────
CREATE INDEX idx_contacts_account_id     ON contacts(account_id);
CREATE INDEX idx_interactions_account_id ON interactions(account_id);
CREATE INDEX idx_rfqs_account_id         ON rfqs(account_id);
CREATE INDEX idx_quotes_rfq_id           ON quotes(rfq_id);
CREATE INDEX idx_inventory_product_id    ON inventory(product_id);
CREATE INDEX idx_reservations_rfq_id     ON rfq_inventory_reservations(rfq_id);
CREATE INDEX idx_reservations_product_id ON rfq_inventory_reservations(product_id);
CREATE INDEX idx_campaign_audience_campaign_id ON campaign_audience(campaign_id);
CREATE INDEX idx_campaign_audience_account_id  ON campaign_audience(account_id);
CREATE INDEX idx_campaign_audience_contact_id  ON campaign_audience(contact_id);
CREATE INDEX idx_campaigns_created_by_user_id  ON campaigns(created_by_user_id);

-- ── Customer list: sort + per-column filter dropdowns ───────────────────────
CREATE INDEX idx_accounts_name     ON accounts(account_name);
CREATE INDEX idx_accounts_industry ON accounts(industry);
CREATE INDEX idx_accounts_source   ON accounts(source);

-- ── RFQ list / pipeline board ───────────────────────────────────────────────
CREATE INDEX idx_rfqs_stage      ON rfqs(stage);
CREATE INDEX idx_rfqs_created_at ON rfqs(created_at);
CREATE INDEX idx_rfqs_updated_at ON rfqs(updated_at);

-- ── Campaign list ───────────────────────────────────────────────────────────
CREATE INDEX idx_campaigns_status     ON campaigns(status);
CREATE INDEX idx_campaigns_type       ON campaigns(campaign_type);
CREATE INDEX idx_campaigns_sent_count ON campaigns(sent_count);
CREATE INDEX idx_campaigns_created_at ON campaigns(created_at);

-- ── Inventory list + ledger ─────────────────────────────────────────────────
-- products.sku is already UNIQUE, so it needs no separate index.
CREATE INDEX idx_products_product_name        ON products(product_name);
CREATE INDEX idx_products_price               ON products(price);
CREATE INDEX idx_inventory_available_quantity ON inventory(available_quantity);
CREATE INDEX idx_inventory_movements_product_id    ON inventory_movements(product_id);
CREATE INDEX idx_inventory_movements_created_at    ON inventory_movements(created_at);
CREATE INDEX idx_inventory_movements_user_id       ON inventory_movements(user_id);
CREATE INDEX idx_inventory_movements_movement_type ON inventory_movements(movement_type);

-- ── Admin user list ─────────────────────────────────────────────────────────
-- users.email is already UNIQUE; only the other sortable columns need indexes.
CREATE INDEX idx_users_name       ON users(name);
CREATE INDEX idx_users_created_at ON users(created_at);

-- ── Dashboard / reporting composites ────────────────────────────────────────
-- quotesExpiringSoon orders by validity_end_date.
CREATE INDEX idx_quotes_validity_end_date ON quotes(validity_end_date);
-- upcomingScheduledSends: WHERE status='Scheduled' AND scheduled_at >= NOW().
CREATE INDEX idx_campaigns_status_scheduled_at ON campaigns(status, scheduled_at);
-- campaignMomentum: WHERE created_at BETWEEN, covering status for the SUM().
CREATE INDEX idx_campaigns_created_at_status ON campaigns(created_at, status);
-- campaignMomentum segment subqueries over campaign_audience.
CREATE INDEX idx_campaign_audience_campaign_account ON campaign_audience(campaign_id, account_id);
CREATE INDEX idx_campaign_audience_campaign_contact ON campaign_audience(campaign_id, contact_id);
-- Recent Interactions ordering.
CREATE INDEX idx_interactions_interaction_date ON interactions(interaction_date);
-- Win Rate by Account aggregation.
CREATE INDEX idx_rfqs_account_stage ON rfqs(account_id, stage);
