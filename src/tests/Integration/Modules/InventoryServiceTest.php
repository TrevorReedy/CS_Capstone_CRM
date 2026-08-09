<?php
declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Modules\Inventory\InventoryService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\IntegrationTestCase;

/**
 * The inventory reservation lifecycle — the one place in this application where
 * a bug corrupts data rather than just showing the wrong number. Every path
 * moves stock between three places at once (available, reserved, and the
 * movements ledger), and they have to agree afterwards.
 */
#[CoversClass(InventoryService::class)]
final class InventoryServiceTest extends IntegrationTestCase
{
    private InventoryService $service;
    private int $rfqId;
    private int $otherRfqId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryService();

        $rfqs = $this->db->query('SELECT id FROM rfqs ORDER BY id LIMIT 2')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(2, $rfqs, 'seed.sql should provide at least two RFQs');
        [$this->rfqId, $this->otherRfqId] = array_map('intval', $rfqs);
    }

    private function makeProduct(int $quantity = 100, int $threshold = 10): int
    {
        return $this->service->createProduct(
            'Test product ' . uniqid(),
            'SKU-' . uniqid(),
            19.99,
            'Created by the integration suite.',
            $quantity,
            $threshold
        );
    }

    private function stock(int $productId): array
    {
        return $this->fetchOne(
            'SELECT available_quantity, reserved_quantity FROM inventory WHERE product_id = ?',
            [$productId]
        );
    }

    // ── Product creation ────────────────────────────────────────────────────

    #[Test]
    public function creating_a_product_writes_stock_and_a_ledger_entry_together(): void
    {
        $id = $this->makeProduct(42);

        $this->assertSame(42, (int) $this->stock($id)['available_quantity']);
        $this->assertSame(0, (int) $this->stock($id)['reserved_quantity']);
        $this->assertSame(1, $this->rowCount('inventory_movements', 'product_id = ? AND movement_type = ?', [$id, 'created']));
    }

    #[Test]
    public function a_duplicate_sku_is_refused(): void
    {
        $sku = 'SKU-DUPLICATE-' . uniqid();
        $this->service->createProduct('First', $sku, 1.00, null, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already in use');
        $this->service->createProduct('Second', $sku, 1.00, null, 5);
    }

    /**
     * createProduct() runs inside transactional(). If the ledger write fails
     * after the product row lands, neither may survive — a product with no
     * inventory row is invisible to every list query but still holds its SKU.
     */
    #[Test]
    public function a_failed_creation_leaves_nothing_behind(): void
    {
        $before = $this->rowCount('products');

        try {
            $this->service->createProduct('Bad price', 'SKU-' . uniqid(), -1.00, null, 5);
            $this->fail('a negative price should have been rejected');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame($before, $this->rowCount('products'));
    }

    #[Test]
    public function product_input_is_validated_before_anything_is_written(): void
    {
        foreach ([
            ['', 'SKU-' . uniqid(), 1.0, 5, 10],          // no name
            ['Name', '', 1.0, 5, 10],                      // no SKU
            ['Name', 'SKU-' . uniqid(), 1.0, -1, 10],      // negative starting quantity
            ['Name', 'SKU-' . uniqid(), 1.0, 5, -1],       // negative threshold
        ] as [$name, $sku, $price, $qty, $threshold]) {
            try {
                $this->service->createProduct($name, $sku, $price, null, $qty, $threshold);
                $this->fail("should have rejected: " . json_encode([$name, $sku, $price, $qty, $threshold]));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    // ── The low-stock flag ──────────────────────────────────────────────────

    #[Test]
    public function a_product_below_its_own_threshold_is_flagged_low(): void
    {
        $low  = $this->makeProduct(3, 10);
        $fine = $this->makeProduct(50, 10);

        $rows = [];
        foreach ($this->service->getProductList() as $row) {
            $rows[(int) $row['id']] = $row;
        }

        $this->assertTrue($rows[$low]['low_stock']);
        $this->assertFalse($rows[$fine]['low_stock']);
    }

    /**
     * The threshold is per product (migration 011), not a global constant — a
     * product with a threshold of 100 is low at 50, and one with a threshold of
     * 1 is not.
     */
    #[Test]
    public function the_low_stock_threshold_is_per_product(): void
    {
        $sensitive = $this->makeProduct(50, 100);
        $tolerant  = $this->makeProduct(50, 1);

        $rows = [];
        foreach ($this->service->getProductList() as $row) {
            $rows[(int) $row['id']] = $row;
        }

        $this->assertTrue($rows[$sensitive]['low_stock']);
        $this->assertFalse($rows[$tolerant]['low_stock']);
    }

    /** Exactly at the threshold is not below it. */
    #[Test]
    public function a_product_exactly_at_its_threshold_is_not_low(): void
    {
        $id = $this->makeProduct(10, 10);

        $rows = [];
        foreach ($this->service->getProductList() as $row) {
            $rows[(int) $row['id']] = $row;
        }

        $this->assertFalse($rows[$id]['low_stock']);
    }

    // ── Reserve ─────────────────────────────────────────────────────────────

    #[Test]
    public function reserving_moves_stock_from_available_to_reserved(): void
    {
        $id = $this->makeProduct(100);

        $this->service->reserveForRfq($this->rfqId, $id, 30);

        $stock = $this->stock($id);
        $this->assertSame(70, (int) $stock['available_quantity']);
        $this->assertSame(30, (int) $stock['reserved_quantity']);
    }

    #[Test]
    public function reserving_records_a_reservation_and_a_ledger_entry(): void
    {
        $id = $this->makeProduct(100);

        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 30);

        $reservation = $this->fetchOne('SELECT * FROM rfq_inventory_reservations WHERE id = ?', [$reservationId]);
        $this->assertSame('Reserved', $reservation['reservation_status']);
        $this->assertSame(30, (int) $reservation['quantity_reserved']);

        $movement = $this->fetchOne(
            "SELECT * FROM inventory_movements WHERE product_id = ? AND movement_type = 'reserved'",
            [$id]
        );
        $this->assertNotNull($movement);
        $this->assertSame(-30, (int) $movement['quantity_delta'], 'a reservation removes from available stock');
    }

    /**
     * The rule that keeps the whole ledger honest: you cannot promise stock you
     * do not have. Documented as intentional — a backorder feature is the
     * follow-up, not a silent negative balance.
     */
    #[Test]
    public function reserving_more_than_is_available_is_refused(): void
    {
        $id = $this->makeProduct(10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot reserve more than the available quantity');
        $this->service->reserveForRfq($this->rfqId, $id, 11);
    }

    #[Test]
    public function reserving_exactly_the_available_quantity_is_allowed(): void
    {
        $id = $this->makeProduct(10);

        $this->service->reserveForRfq($this->rfqId, $id, 10);

        $this->assertSame(0, (int) $this->stock($id)['available_quantity']);
        $this->assertSame(10, (int) $this->stock($id)['reserved_quantity']);
    }

    #[Test]
    public function a_refused_reservation_changes_no_stock(): void
    {
        $id = $this->makeProduct(10);

        try {
            $this->service->reserveForRfq($this->rfqId, $id, 999);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(10, (int) $this->stock($id)['available_quantity']);
        $this->assertSame(0, (int) $this->stock($id)['reserved_quantity']);
        $this->assertSame(0, $this->rowCount('rfq_inventory_reservations', 'product_id = ?', [$id]));
    }

    #[Test]
    public function a_non_positive_reservation_quantity_is_refused(): void
    {
        $id = $this->makeProduct(10);

        $this->expectException(InvalidArgumentException::class);
        $this->service->reserveForRfq($this->rfqId, $id, 0);
    }

    #[Test]
    public function reserving_against_a_missing_product_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found');
        $this->service->reserveForRfq($this->rfqId, 987654, 1);
    }

    // ── Release ─────────────────────────────────────────────────────────────

    #[Test]
    public function releasing_returns_the_stock_to_available(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 30);

        $this->service->releaseReservation($reservationId);

        $stock = $this->stock($id);
        $this->assertSame(100, (int) $stock['available_quantity'], 'released stock comes back');
        $this->assertSame(0, (int) $stock['reserved_quantity']);

        $reservation = $this->fetchOne('SELECT reservation_status FROM rfq_inventory_reservations WHERE id = ?', [$reservationId]);
        $this->assertSame('Released', $reservation['reservation_status']);
    }

    #[Test]
    public function a_reservation_cannot_be_released_twice(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 30);
        $this->service->releaseReservation($reservationId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only active reservations can be released');
        $this->service->releaseReservation($reservationId);
    }

    // ── Convert ─────────────────────────────────────────────────────────────

    /**
     * Converting means sold and shipped: the stock leaves reserved and does NOT
     * come back to available. Getting this backwards would invent inventory.
     */
    #[Test]
    public function converting_consumes_the_stock_permanently(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 30);

        $this->service->convertReservation($reservationId);

        $stock = $this->stock($id);
        $this->assertSame(70, (int) $stock['available_quantity'], 'converted stock must NOT return to available');
        $this->assertSame(0, (int) $stock['reserved_quantity']);

        $reservation = $this->fetchOne('SELECT reservation_status FROM rfq_inventory_reservations WHERE id = ?', [$reservationId]);
        $this->assertSame('Converted', $reservation['reservation_status']);
    }

    #[Test]
    public function a_converted_reservation_cannot_be_released(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 30);
        $this->service->convertReservation($reservationId);

        $this->expectException(RuntimeException::class);
        $this->service->releaseReservation($reservationId);
    }

    // ── Manual adjustment ───────────────────────────────────────────────────

    #[Test]
    public function a_manual_adjustment_logs_the_delta_not_the_new_total(): void
    {
        $id = $this->makeProduct(100);

        $this->service->updateStock($id, 130);

        $movement = $this->fetchOne(
            "SELECT quantity_delta FROM inventory_movements
              WHERE product_id = ? AND movement_type = 'manual_adjustment'",
            [$id]
        );
        $this->assertSame(30, (int) $movement['quantity_delta']);
        $this->assertSame(130, (int) $this->stock($id)['available_quantity']);
    }

    #[Test]
    public function a_manual_adjustment_cannot_make_stock_negative(): void
    {
        $id = $this->makeProduct(100);

        $this->expectException(InvalidArgumentException::class);
        $this->service->updateStock($id, -1);
    }

    /**
     * reserved_quantity is owned exclusively by the reservation flow, so a
     * manual stock edit must leave it untouched — otherwise a product's
     * reserved count stops tracing back to real reservation rows.
     */
    #[Test]
    public function a_manual_adjustment_leaves_reserved_stock_alone(): void
    {
        $id = $this->makeProduct(100);
        $this->service->reserveForRfq($this->rfqId, $id, 40);

        $this->service->updateStock($id, 5);

        $stock = $this->stock($id);
        $this->assertSame(5, (int) $stock['available_quantity']);
        $this->assertSame(40, (int) $stock['reserved_quantity']);
    }

    // ── Deletion ────────────────────────────────────────────────────────────

    #[Test]
    public function a_product_with_an_active_reservation_cannot_be_deleted(): void
    {
        $id = $this->makeProduct(100);
        $this->service->reserveForRfq($this->rfqId, $id, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active reservation');
        $this->service->deleteProduct($id);
    }

    #[Test]
    public function a_product_that_was_never_reserved_can_be_deleted(): void
    {
        $id = $this->makeProduct(100);

        $this->service->deleteProduct($id);

        $this->assertSame(0, $this->rowCount('products', 'id = ?', [$id]));
    }

    /**
     * A released reservation is still a row, and the foreign key is RESTRICT, so
     * the product genuinely cannot be deleted. What matters is that the caller
     * is told that clearly instead of being handed an integrity error — the old
     * guard counted only active reservations and advised "release or convert
     * them first", which was advice that could not be followed.
     */
    #[Test]
    public function a_product_with_a_released_reservation_reports_why_it_cannot_be_deleted(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 10);
        $this->service->releaseReservation($reservationId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('past reservation');
        $this->service->deleteProduct($id);
    }

    /**
     * The failed delete must not leave a "deleted" entry in the ledger for a
     * product that is still there. logMovement() has to run before delete()
     * (it reads the product row for its snapshot), so both live in one
     * transaction and a refused delete takes the log entry with it.
     */
    #[Test]
    public function a_refused_delete_leaves_no_deleted_entry_in_the_ledger(): void
    {
        $id            = $this->makeProduct(100);
        $reservationId = $this->service->reserveForRfq($this->rfqId, $id, 10);
        $this->service->releaseReservation($reservationId);

        try {
            $this->service->deleteProduct($id);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1, $this->rowCount('products', 'id = ?', [$id]), 'the product survives');
        $this->assertSame(
            0,
            $this->rowCount('inventory_movements', "product_id = ? AND movement_type = 'deleted'", [$id]),
            'the ledger must not claim a surviving product was deleted'
        );
    }

    // ── The ledger as an audit trail ────────────────────────────────────────

    /**
     * inventory_movements is the only real audit trail in the application. A
     * full reserve/release/convert cycle should leave a readable history rather
     * than just a final number.
     */
    #[Test]
    public function the_ledger_records_every_step_of_a_reservation_cycle(): void
    {
        $id = $this->makeProduct(100);

        // Two different RFQs on purpose: uq_reservations_rfq_product (migration
        // 020) allows only one row per RFQ/product pair, and a released row
        // still occupies it — so the same product cannot be re-reserved on the
        // same RFQ even after release.
        $one = $this->service->reserveForRfq($this->rfqId, $id, 10);
        $this->service->releaseReservation($one);
        $two = $this->service->reserveForRfq($this->otherRfqId, $id, 20);
        $this->service->convertReservation($two);

        $types = $this->db->query(
            "SELECT movement_type FROM inventory_movements WHERE product_id = {$id} ORDER BY id"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(['created', 'reserved', 'released', 'reserved', 'converted'], $types);
    }
}
