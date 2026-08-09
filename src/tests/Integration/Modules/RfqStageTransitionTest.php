<?php
declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Modules\Inventory\InventoryService;
use App\Modules\RFQ\RFQRepository;
use App\Modules\RFQ\RFQService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Winning an RFQ converts its inventory reservations — held stock becomes sold
 * stock. This is the one cross-module write in the application: an RFQ stage
 * change reaching into inventory.
 *
 * It needs its own class because RFQRepository::updateReservationStatus() calls
 * beginTransaction() unconditionally, so it cannot run inside the base class's
 * per-test transaction (see the note on the atomicity test below). Cleanup is
 * therefore manual: everything this class writes is recorded and removed in
 * tearDown.
 */
#[CoversClass(RFQService::class)]
final class RfqStageTransitionTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var int[] reservation ids to remove in tearDown */
    private array $reservations = [];

    /** @var array<int, string> rfq id => the stage it had before the test */
    private array $originalStages = [];

    /** @var array<int, array{available:int, reserved:int}> product id => stock before the test */
    private array $originalStock = [];

    protected function tearDown(): void
    {
        foreach ($this->reservations as $id) {
            $this->db->prepare('DELETE FROM rfq_inventory_reservations WHERE id = ?')->execute([$id]);
        }
        foreach ($this->originalStages as $rfqId => $stage) {
            $this->db->prepare('UPDATE rfqs SET stage = ? WHERE id = ?')->execute([$stage, $rfqId]);
        }
        foreach ($this->originalStock as $productId => $stock) {
            $this->db->prepare(
                'UPDATE inventory SET available_quantity = ?, reserved_quantity = ? WHERE product_id = ?'
            )->execute([$stock['available'], $stock['reserved'], $productId]);
        }
        $this->db->exec('DELETE FROM inventory_movements WHERE note LIKE \'%RFQ #%\' AND product_id IS NULL');

        $this->reservations   = [];
        $this->originalStages = [];
        $this->originalStock  = [];

        parent::tearDown();
    }

    /** An RFQ that is not yet Won, remembered so tearDown can restore its stage. */
    private function borrowRfq(): int
    {
        $row = $this->fetchOne(
            "SELECT id, stage FROM rfqs
              WHERE stage <> 'Won'
                AND id NOT IN (SELECT rfq_id FROM rfq_inventory_reservations)
              ORDER BY id LIMIT 1"
        );
        $this->assertNotNull($row, 'need an RFQ that is not Won and has no reservations');

        $this->originalStages[(int) $row['id']] = $row['stage'];

        return (int) $row['id'];
    }

    /**
     * Reserve through InventoryService rather than inserting the row directly:
     * a reservation is only half of the operation, and a row whose quantity is
     * not also reflected in inventory.reserved_quantity makes the later convert
     * drive the counter negative and trip chk_inventory_reserved.
     */
    private function reserve(int $rfqId, int $productId, int $quantity = 2): int
    {
        if (!isset($this->originalStock[$productId])) {
            $stock = $this->fetchOne(
                'SELECT available_quantity, reserved_quantity FROM inventory WHERE product_id = ?',
                [$productId]
            );
            $this->originalStock[$productId] = [
                'available' => (int) $stock['available_quantity'],
                'reserved'  => (int) $stock['reserved_quantity'],
            ];
        }

        $id = (new InventoryService())->reserveForRfq($rfqId, $productId, $quantity);
        $this->reservations[] = $id;

        return $id;
    }

    /** @return int[] */
    private function productsWithStock(int $count): array
    {
        $rows = $this->db->query(
            "SELECT p.id FROM products p
               JOIN inventory i ON i.product_id = p.id
              WHERE i.available_quantity > 5
              ORDER BY p.id LIMIT {$count}"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertCount($count, $rows, 'seed.sql should provide products with stock');

        return array_map('intval', $rows);
    }

    #[Test]
    public function winning_an_rfq_converts_its_reservation(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = $this->borrowRfq();
        [$productId] = $this->productsWithStock(1);

        $this->reserve($rfqId, $productId, 2);
        $held = $this->fetchOne(
            'SELECT available_quantity, reserved_quantity FROM inventory WHERE product_id = ?',
            [$productId]
        );

        $service->changeStage($rfqId, 'Won');

        $this->assertSame('Won', $this->fetchOne('SELECT stage FROM rfqs WHERE id = ?', [$rfqId])['stage']);
        $this->assertSame(
            0,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Reserved'", [$rfqId]),
            'no reservation should still be Reserved once the RFQ is Won'
        );

        // Converting takes the stock out of reserved and does NOT hand it back
        // to available — it has been sold.
        $sold = $this->fetchOne(
            'SELECT available_quantity, reserved_quantity FROM inventory WHERE product_id = ?',
            [$productId]
        );
        $this->assertSame((int) $held['reserved_quantity'] - 2, (int) $sold['reserved_quantity']);
        $this->assertSame((int) $held['available_quantity'], (int) $sold['available_quantity']);
    }

    /**
     * Moving to a stage that is not Won must leave reservations alone — stock
     * stays held while the deal is still in play.
     */
    #[Test]
    public function moving_to_a_non_winning_stage_leaves_reservations_held(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = $this->borrowRfq();
        [$productId] = $this->productsWithStock(1);

        $this->reserve($rfqId, $productId, 2);

        $service->changeStage($rfqId, 'Negotiation');

        $this->assertSame(
            1,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Reserved'", [$rfqId])
        );
    }

    /**
     * Winning an RFQ that holds several products converts all of them or none.
     * Previously each reservation was converted in its own transaction, so a
     * failure partway through left the RFQ marked Won with some products sold
     * and others still held — and nothing would revisit the stragglers, because
     * the stage had already been written.
     */
    #[Test]
    public function winning_a_multi_product_rfq_converts_every_reservation(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = $this->borrowRfq();
        $products = $this->productsWithStock(3);

        foreach ($products as $productId) {
            $this->reserve($rfqId, $productId, 2);
        }

        $service->changeStage($rfqId, 'Won');

        $this->assertSame(
            3,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Converted'", [$rfqId]),
            'every reservation on a won RFQ should convert'
        );
        $this->assertSame(
            0,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Reserved'", [$rfqId])
        );
    }

    /**
     * The nesting guard: a caller that wraps a stage change in its own
     * transaction used to get PDO's "There is already an active transaction",
     * because updateReservationStatus() opened one unconditionally. It now
     * joins an open transaction the way InventoryService::transactional() does.
     */
    #[Test]
    public function a_stage_change_can_run_inside_a_callers_transaction(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = $this->borrowRfq();
        [$productId] = $this->productsWithStock(1);
        $this->reserve($rfqId, $productId, 2);

        $this->db->beginTransaction();
        try {
            $service->changeStage($rfqId, 'Won');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->assertSame('Won', $this->fetchOne('SELECT stage FROM rfqs WHERE id = ?', [$rfqId])['stage']);
        $this->assertSame(
            0,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Reserved'", [$rfqId])
        );
    }

    /**
     * The atomicity claim, tested by making the conversion fail: an RFQ left
     * mid-conversion must roll back to fully-held, not sit half sold.
     */
    #[Test]
    public function a_failure_partway_through_winning_rolls_the_whole_thing_back(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = $this->borrowRfq();
        $products = $this->productsWithStock(2);

        foreach ($products as $productId) {
            $this->reserve($rfqId, $productId, 2);
        }
        $stageBefore = $this->fetchOne('SELECT stage FROM rfqs WHERE id = ?', [$rfqId])['stage'];

        // Drive the second conversion into the chk_inventory_reserved CHECK
        // constraint by zeroing the reserved counter it will try to decrement.
        $this->db->prepare('UPDATE inventory SET reserved_quantity = 0 WHERE product_id = ?')
                 ->execute([$products[1]]);

        try {
            $service->changeStage($rfqId, 'Won');
            $this->fail('the conversion should have failed on the constraint');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(
            $stageBefore,
            $this->fetchOne('SELECT stage FROM rfqs WHERE id = ?', [$rfqId])['stage'],
            'the stage change must roll back with the conversions'
        );
        $this->assertSame(
            0,
            $this->rowCount('rfq_inventory_reservations', "rfq_id = ? AND reservation_status = 'Converted'", [$rfqId]),
            'no reservation should be left converted after a failed win'
        );
    }
}
