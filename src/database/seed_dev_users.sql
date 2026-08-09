-- seed_dev_users.sql — one user per role, for testing role-based access.
--
-- NOT loaded automatically. seed.sql creates only a single Super Admin, which
-- makes it impossible to check that the permission matrix actually restricts
-- anything: every page looks reachable when you are the one role that bypasses
-- every check.
--
-- All five share the same demo password as the seeded admin: `password`.
-- Development only. Never load this into a deployed environment.
--
--   docker compose exec -T db mysql -uroot -pdevonly_root typhon_cath_crm \
--     < src/database/seed_dev_users.sql

INSERT INTO users (name, email, password_hash, role_id)
SELECT v.name, v.email, '$2y$10$ZtXz2SAiwlR1ZttmF9EZqesRX1BqN.cgTfmG2bXV4LKU/bK5O8Gi6', r.id
FROM (
        SELECT 'Admin Tester'     AS name, 'admin2@typhoncath.test' AS email, 'Admin'             AS role
  UNION SELECT 'Sales Tester',          'sales@typhoncath.test',           'Sales User'
  UNION SELECT 'Marketing Tester',      'mktg@typhoncath.test',            'Marketing User'
  UNION SELECT 'Inventory Tester',      'inv@typhoncath.test',             'Inventory Manager'
) v
JOIN roles r ON r.role_name = v.role
ON DUPLICATE KEY UPDATE
    name    = VALUES(name),
    role_id = VALUES(role_id);

SELECT u.email, r.role_name, 'password' AS login_password
  FROM users u JOIN roles r ON r.id = u.role_id
 ORDER BY u.id;
