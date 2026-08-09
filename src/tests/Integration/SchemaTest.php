<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Proves the checked-in SQL actually builds a working database. If this fails,
 * every other integration test is meaningless and `docker compose up` is broken
 * for the next person who clones the repo.
 */
final class SchemaTest extends IntegrationTestCase
{
    #[Test]
    public function every_table_the_application_queries_exists(): void
    {
        $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            'roles', 'users', 'role_permissions', 'accounts', 'rfqs', 'quotes',
            'campaigns', 'products', 'inventory', 'inventory_movements',
            'rfq_inventory_reservations',
        ] as $table) {
            $this->assertContains($table, $tables, "missing table: {$table}");
        }
    }

    #[Test]
    public function the_seed_creates_the_five_documented_roles(): void
    {
        $roles = $this->db->query('SELECT role_name FROM roles')->fetchAll(PDO::FETCH_COLUMN);

        foreach (['Super Admin', 'Admin', 'Sales User', 'Marketing User', 'Inventory Manager'] as $role) {
            $this->assertContains($role, $roles);
        }
    }

    #[Test]
    public function the_demo_admin_is_seeded_as_a_super_admin(): void
    {
        $user = $this->fetchOne(
            'SELECT u.email, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            ['admin@typhoncath.test']
        );

        $this->assertNotNull($user);
        $this->assertSame('Super Admin', $user['role_name']);
    }

    /**
     * The README calls indexes.sql non-optional because RFQ, account and
     * campaign search all use MySQL FULLTEXT, and a missing index is not a
     * degraded search — it is a hard error ("Can't find FULLTEXT index matching
     * the column list"). This asserts the index exists rather than waiting for
     * a search test to fail with a confusing message.
     */
    #[Test]
    public function the_fulltext_indexes_from_indexes_sql_are_present(): void
    {
        $indexes = $this->db->query(
            "SELECT DISTINCT INDEX_NAME
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND INDEX_TYPE = 'FULLTEXT'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotEmpty($indexes, 'indexes.sql did not load — FULLTEXT search will fail');
        $this->assertContains('ft_accounts_name', $indexes);
    }

    /**
     * The CHECK constraints added in migration 020 are the last line of defence
     * behind the service-layer validation. Confirm the database really rejects
     * what the services refuse to send.
     */
    #[Test]
    public function negative_inventory_is_rejected_by_the_database(): void
    {
        $this->db->exec("INSERT INTO products (id, product_name, sku, price) VALUES (9001, 'Test widget', 'TEST-9001', 10.00)");

        $this->expectException(\PDOException::class);
        $this->db->exec('INSERT INTO inventory (product_id, available_quantity) VALUES (9001, -5)');
    }

    #[Test]
    public function a_quote_discount_larger_than_its_amount_is_rejected_by_the_database(): void
    {
        $rfq = $this->fetchOne('SELECT id FROM rfqs LIMIT 1');
        $this->assertNotNull($rfq, 'seed.sql should provide at least one RFQ');

        $this->expectException(\PDOException::class);
        $stmt = $this->db->prepare('INSERT INTO quotes (rfq_id, quote_amount, discount) VALUES (?, 100, 500)');
        $stmt->execute([$rfq['id']]);
    }

    /**
     * Added by migration 020: stacking rows for the same product on one RFQ made
     * the reserved totals impossible to reconcile against the ledger.
     */
    #[Test]
    public function the_same_product_cannot_be_reserved_twice_on_one_rfq(): void
    {
        $rfq     = $this->fetchOne('SELECT id FROM rfqs LIMIT 1');
        $product = $this->fetchOne('SELECT id FROM products LIMIT 1');
        $this->assertNotNull($rfq);
        $this->assertNotNull($product);

        $insert = $this->db->prepare(
            'INSERT INTO rfq_inventory_reservations (rfq_id, product_id, quantity_reserved) VALUES (?, ?, 1)'
        );
        $insert->execute([$rfq['id'], $product['id']]);

        $this->expectException(\PDOException::class);
        $insert->execute([$rfq['id'], $product['id']]);
    }

    /**
     * Migration 019 dropped open_rate/click_rate because they held generated
     * numbers that looked like measurements. If a future schema change brings
     * them back, that is a decision worth making on purpose.
     */
    #[Test]
    public function the_simulated_campaign_rate_columns_stay_dropped(): void
    {
        $columns = $this->db->query('SHOW COLUMNS FROM campaigns')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotContains('open_rate', $columns);
        $this->assertNotContains('click_rate', $columns);
        $this->assertContains('sent_count', $columns);
    }
}
