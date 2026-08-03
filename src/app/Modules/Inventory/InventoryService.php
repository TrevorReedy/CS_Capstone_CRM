<?php
namespace App\Modules\Inventory;

// Business rules for the Inventory module: product validation, stock
// adjustments, and reservation lifecycle (reserve / release / convert).
class InventoryService
{
    private InventoryRepository $repo;

    /** Default low-stock threshold applied when a caller doesn't specify one. */
    public const DEFAULT_LOW_STOCK_THRESHOLD = 10;

    public function __construct()
    {
        $this->repo = new InventoryRepository();
    }

    /**
     * Run $work inside a single database transaction.
     *
     * Every write path here touches more than one table — products + inventory
     * + the movements ledger — and each was issued as an independent statement.
     * A failure partway through left a product with no inventory row, or stock
     * that disagreed with the ledger that is supposed to audit it. Nested calls
     * join the outer transaction rather than opening a second one.
     *
     * @template T
     * @param callable():T $work
     * @return T
     */
    private function transactional(callable $work): mixed
    {
        $db = \App\Core\Database::connection();

        if ($db->inTransaction()) {
            return $work();
        }

        $db->beginTransaction();
        try {
            $result = $work();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get the product list, optionally searched/filtered/sorted, with a
     * computed 'low_stock' flag (available_quantity below that product's own
     * low_stock_threshold) added to each row for the view.
     *
     * @param string[] $statuses subset of InventoryRepository::$statuses — a
     *                            column filter on the Status column.
     */
    public function getProductList(?string $search = null, array $statuses = [], string $sortCol = 'product_name', string $sortDir = 'ASC', ?int $limit = null, int $offset = 0): array
    {
        $products = $this->repo->all($search, $statuses, $sortCol, $sortDir, $limit, $offset);

        foreach ($products as &$product) {
            $available = (int) ($product['available_quantity'] ?? 0);
            $threshold = (int) ($product['low_stock_threshold'] ?? self::DEFAULT_LOW_STOCK_THRESHOLD);
            $product['low_stock'] = $available < $threshold;
        }

        return $products;
    }

    // Total products matching the same filters (for pagination).
    public function getProductCount(?string $search = null, array $statuses = []): int
    {
        return $this->repo->count($search, $statuses);
    }

    /**
     * Get a single product's detail, or null if it doesn't exist.
     */
    public function getProductDetail(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    /**
     * Business rule: validate and create a new product + starting stock.
     * Returns the new product id, or throws on invalid input.
     */
    public function createProduct(string $productName, string $sku, float $price, ?string $description, int $startingQuantity, int $lowStockThreshold = self::DEFAULT_LOW_STOCK_THRESHOLD, ?int $userId = null): int
    {
        if (trim($productName) === '') {
            throw new \InvalidArgumentException('Product name is required.');
        }
        if (trim($sku) === '') {
            throw new \InvalidArgumentException('SKU is required.');
        }
        if ($price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative.');
        }
        if ($startingQuantity < 0) {
            throw new \InvalidArgumentException('Starting quantity cannot be negative.');
        }
        if ($lowStockThreshold < 0) {
            throw new \InvalidArgumentException('Low stock threshold cannot be negative.');
        }
        if ($this->repo->findBySku($sku) !== null) {
            throw new \InvalidArgumentException("SKU \"{$sku}\" is already in use by another product.");
        }

        // products + inventory + ledger must land together or not at all.
        return $this->transactional(function () use ($productName, $sku, $price, $description, $startingQuantity, $lowStockThreshold, $userId) {
            $productId = $this->repo->create($productName, $sku, $price, $description, $startingQuantity, $lowStockThreshold);
            $this->repo->logMovement($productId, $userId, 'created', $startingQuantity, 'Product created.');

            return $productId;
        });
    }

    /**
     * Business rule: validate and apply product detail edits.
     */
    public function updateProduct(int $id, string $productName, string $sku, float $price, ?string $description, int $lowStockThreshold, ?int $userId = null): bool
    {
        if (trim($productName) === '') {
            throw new \InvalidArgumentException('Product name is required.');
        }
        if (trim($sku) === '') {
            throw new \InvalidArgumentException('SKU is required.');
        }
        if ($price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative.');
        }
        if ($lowStockThreshold < 0) {
            throw new \InvalidArgumentException('Low stock threshold cannot be negative.');
        }
        $existing = $this->repo->findBySku($sku);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException("SKU \"{$sku}\" is already in use by another product.");
        }

        return $this->transactional(function () use ($id, $productName, $sku, $price, $description, $lowStockThreshold, $userId) {
            $result = $this->repo->updateProduct($id, $productName, $sku, $price, $description);
            $this->repo->updateLowStockThreshold($id, $lowStockThreshold);
            $this->repo->logMovement($id, $userId, 'updated', null, 'Product details updated (name, SKU, price, description, and/or low stock threshold).');

            return $result;
        });
    }

    /**
     * Business rule: manual stock edits set only the available quantity and can
     * never go negative. Reserved quantity is NOT editable here — it is owned and
     * kept in sync exclusively by the RFQ reservation flow (reserve/release/
     * convert), so a product's reserved count always traces back to real
     * reservation rows.
     */
    public function updateStock(int $productId, int $availableQuantity, ?int $userId = null): bool
    {
        if ($availableQuantity < 0) {
            throw new \InvalidArgumentException('Available quantity cannot be negative.');
        }

        return $this->transactional(function () use ($productId, $availableQuantity, $userId) {
            $before = $this->repo->findById($productId);
            $delta  = $before !== null ? $availableQuantity - (int) $before['available_quantity'] : null;

            $result = $this->repo->updateAvailableQuantity($productId, $availableQuantity);
            $this->repo->logMovement($productId, $userId, 'manual_adjustment', $delta, 'Manual stock adjustment.');

            return $result;
        });
    }

    /**
     * Business rule: delete a product.
     * Blocked if the product has active (Reserved) reservations —
     * those must be released or converted first.
     */
    public function deleteProduct(int $id, ?int $userId = null): bool
    {
        $active = $this->repo->countActiveReservations($id);
        if ($active > 0) {
            throw new \RuntimeException(
                "Cannot delete — this product has {$active} active reservation(s). " .
                "Release or convert them first."
            );
        }

        // Log before deleting: logMovement snapshots product_name/sku by
        // reading the product row, which won't exist once delete() runs.
        $this->repo->logMovement($id, $userId, 'deleted', null, 'Product deleted.');
        return $this->repo->delete($id);
    }

    /**
     * Get all inventory reservations, joined with product/RFQ info.
     */
    public function getReservations(): array
    {
        return $this->repo->allReservations();
    }

    /**
     * Inventory Ledger: paged/filtered/sorted movement rows for the report
     * and print views. $limit === null means "no limit" (used by the print
     * view, which always renders every matching row).
     */
    public function getLedger(?int $productId, string $search, array $movementTypes, string $sortCol, string $sortDir, ?int $limit, int $offset): array
    {
        return $this->repo->movements($productId, $search, $movementTypes, $sortCol, $sortDir, $limit ?? PHP_INT_MAX, $offset);
    }

    // Total ledger rows matching the same filters (for pagination).
    public function getLedgerCount(?int $productId, string $search, array $movementTypes): int
    {
        return $this->repo->movementsCount($productId, $search, $movementTypes);
    }

    /**
     * Business rule: reserving inventory for an RFQ.
     * - Cannot reserve more than what's currently available.
     * - On success, moves quantity from available -> reserved on the product,
     *   and creates a reservation record linked to the RFQ.
     *
     * TODO: once a backorder feature exists, this is the place to allow a
     * reservation to exceed available_quantity and flag the excess instead
     * of rejecting it outright.
     */
    public function reserveForRfq(int $rfqId, int $productId, int $quantity, ?int $userId = null): int
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Reservation quantity must be greater than zero.');
        }

        $product = $this->repo->findById($productId);
        if ($product === null) {
            throw new \InvalidArgumentException('Product not found.');
        }

        $available = (int) $product['available_quantity'];
        if ($quantity > $available) {
            throw new \RuntimeException('Cannot reserve more than the available quantity.');
        }

        $newAvailable = $available - $quantity;
        $newReserved = (int) $product['reserved_quantity'] + $quantity;
        $this->repo->updateStock($productId, $newAvailable, $newReserved);

        $reservationId = $this->repo->createReservation($rfqId, $productId, $quantity);
        $this->repo->logMovement($productId, $userId, 'reserved', -$quantity, "Reserved for RFQ #{$rfqId}.");

        return $reservationId;
    }

    /**
     * Business rule: releasing a reservation (RFQ was Lost or cancelled)
     * returns the reserved quantity back to available stock.
     */
    public function releaseReservation(int $reservationId, ?int $userId = null): bool
    {
        $reservation = $this->repo->findReservationById($reservationId);
        if ($reservation === null) {
            throw new \InvalidArgumentException('Reservation not found.');
        }
        if ($reservation['reservation_status'] !== 'Reserved') {
            throw new \RuntimeException('Only active reservations can be released.');
        }

        $productId = (int) $reservation['product_id'];
        $qty       = (int) $reservation['quantity_reserved'];
        $product   = $this->repo->findById($productId);
        $newAvailable = (int) $product['available_quantity'] + $qty;
        $newReserved  = (int) $product['reserved_quantity'] - $qty;

        $this->repo->updateStock($productId, $newAvailable, max(0, $newReserved));
        $result = $this->repo->updateReservationStatus($reservationId, 'Released');
        $this->repo->logMovement($productId, $userId, 'released', $qty, "Reservation #{$reservationId} released back to available stock.");

        return $result;
    }

    /**
     * Business rule: converting a reservation (RFQ was Won) permanently
     * removes the stock from reserved (it has been sold/shipped), and
     * does NOT return it to available.
     */
    public function convertReservation(int $reservationId, ?int $userId = null): bool
    {
        $reservation = $this->repo->findReservationById($reservationId);
        if ($reservation === null) {
            throw new \InvalidArgumentException('Reservation not found.');
        }
        if ($reservation['reservation_status'] !== 'Reserved') {
            throw new \RuntimeException('Only active reservations can be converted.');
        }

        $productId = (int) $reservation['product_id'];
        $qty       = (int) $reservation['quantity_reserved'];
        $product   = $this->repo->findById($productId);
        $newReserved = (int) $product['reserved_quantity'] - $qty;

        $this->repo->updateStock($productId, (int) $product['available_quantity'], max(0, $newReserved));
        $result = $this->repo->updateReservationStatus($reservationId, 'Converted');
        $this->repo->logMovement($productId, $userId, 'converted', 0, "Reservation #{$reservationId} converted (sold/shipped).");

        return $result;
    }
}
