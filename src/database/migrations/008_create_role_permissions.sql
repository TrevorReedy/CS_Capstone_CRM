
CREATE TABLE role_permissions (
    role_id    INT          NOT NULL,
    permission VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Super Admin (id=1) is handled by wildcard in Permissions::can() — no rows needed.

-- Admin (id=2): everything except admin self-management
INSERT INTO role_permissions (role_id, permission) VALUES
(2, 'dashboard.view'),
(2, 'customers.view'),    (2, 'customers.create'),   (2, 'customers.edit'),   (2, 'customers.delete'),
(2, 'contacts.view'),     (2, 'contacts.create'),    (2, 'contacts.edit'),    (2, 'contacts.delete'),
(2, 'interactions.create'),
(2, 'rfqs.view'),         (2, 'rfqs.create'),        (2, 'rfqs.edit'),        (2, 'rfqs.delete'),  (2, 'rfqs.update_stage'),
(2, 'quotes.create'),     (2, 'quotes.edit'),        (2, 'quotes.delete'),
(2, 'reservations.create'), (2, 'reservations.update_status'),
(2, 'campaigns.view'),    (2, 'campaigns.create'),   (2, 'campaigns.edit'),   (2, 'campaigns.delete'), (2, 'campaigns.metrics'),
(2, 'inventory.view'),    (2, 'inventory.create'),   (2, 'inventory.edit'),   (2, 'inventory.update_stock'), (2, 'inventory.reserve'),
(2, 'reports.view'),
(2, 'admin.manage_users');

-- Sales User (id=3): customer, RFQ, and inventory view
INSERT INTO role_permissions (role_id, permission) VALUES
(3, 'dashboard.view'),
(3, 'customers.view'),    (3, 'customers.create'),   (3, 'customers.edit'),
(3, 'contacts.view'),     (3, 'contacts.create'),    (3, 'contacts.edit'),
(3, 'interactions.create'),
(3, 'rfqs.view'),         (3, 'rfqs.create'),        (3, 'rfqs.edit'),        (3, 'rfqs.update_stage'),
(3, 'quotes.create'),     (3, 'quotes.edit'),
(3, 'reservations.create'),
(3, 'inventory.view'),
(3, 'reports.view');

-- Marketing User (id=4): campaign-focused, read-only on customers
INSERT INTO role_permissions (role_id, permission) VALUES
(4, 'dashboard.view'),
(4, 'customers.view'),
(4, 'contacts.view'),
(4, 'campaigns.view'),    (4, 'campaigns.create'),   (4, 'campaigns.edit'),   (4, 'campaigns.delete'), (4, 'campaigns.metrics'),
(4, 'reports.view');

-- Inventory Manager (id=5): inventory and reservation management
INSERT INTO role_permissions (role_id, permission) VALUES
(5, 'dashboard.view'),
(5, 'inventory.view'),    (5, 'inventory.create'),   (5, 'inventory.edit'),   (5, 'inventory.update_stock'), (5, 'inventory.reserve'),
(5, 'reservations.create'), (5, 'reservations.update_status'),
(5, 'rfqs.view'),
(5, 'reports.view');
