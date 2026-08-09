<?php
namespace App\Modules\Inventory;

use App\Core\Database;
use App\Core\DataTable\ServerTable;

// Data access layer for products, their stock levels, and RFQ reservations.
// All SQL for the Inventory module lives here — InventoryService owns the
// business rules and calls into these methods.
class InventoryRepository
{
    /** Stock-status column values, for the product list's status filter checkboxes. */
    public static array $statuses = ['In Stock', 'Low Stock', 'No Stock'];

    /**
     * Server-side DataTables source for the products list. Shared by the data
     * and export endpoints. Status is a view-only computed column (available vs.
     * low_stock_threshold), so it is neither ordered nor searched in SQL.
     */
    public static function listTable(): ServerTable
    {
        return new ServerTable(
            Database::connection(),
            'products p LEFT JOIN inventory i ON i.product_id = p.id',
            'p.id, p.sku, p.product_name, p.price,
             COALESCE(i.available_quantity, 0) AS available_quantity,
             COALESCE(i.reserved_quantity, 0)  AS reserved_quantity,
             COALESCE(i.low_stock_threshold, 10) AS low_stock_threshold',
            [
                ['data' => 'sku',          'sql' => 'p.sku',                          'order' => true,  'search' => 'like'],
                ['data' => 'product_name', 'sql' => 'p.product_name',                 'order' => true,  'search' => 'like'],
                ['data' => 'price',        'sql' => 'p.price',                        'order' => true,  'search' => false],
                ['data' => 'available',    'sql' => 'COALESCE(i.available_quantity, 0)', 'order' => true, 'search' => false],
                ['data' => 'reserved',     'sql' => 'COALESCE(i.reserved_quantity, 0)',  'order' => true, 'search' => false],
                ['data' => 'status',       'sql' => '',                               'order' => false, 'search' => false],
                ['data' => 'actions',      'sql' => '',                               'order' => false, 'search' => false],
            ],
            'p.product_name',
            'ASC'
        );
    }
    /**
     * Get all products joined with their inventory counts.
     * Optionally filter by a search term (name or SKU) and/or one or more
     * stock-status values (a column filter on the computed Status column —
     * available_quantity vs. that row's own low_stock_threshold).
     *
     * @param string[] $statuses subset of self::$statuses
     */
    public function all(?string $search = null, array $statuses = [], string $sortCol = 'product_name', string $sortDir = 'ASC', ?int $limit = null, int $offset = 0): array
    {
        $db = Database::connection();

        $colMap = [
            'sku'                => 'p.sku',
            'product_name'       => 'p.product_name',
            'price'              => 'p.price',
            'available_quantity' => 'i.available_quantity',
            'reserved_quantity'  => 'i.reserved_quantity',
        ];
        $orderCol = $colMap[$sortCol] ?? 'p.product_name';
        $orderDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        [$from, $params] = $this->buildProductFrom($search, $statuses);

        $sql = "SELECT p.id, p.product_name, p.sku, p.price, p.description,
                       i.available_quantity, i.reserved_quantity, i.low_stock_threshold
                {$from}
                ORDER BY {$orderCol} {$orderDir}";

        if ($limit !== null) {
            $limit  = max(1, $limit);   // guard the interpolated LIMIT/OFFSET
            $offset = max(0, $offset);
            $sql   .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Total products matching the same filters (for pagination).
    public function count(?string $search = null, array $statuses = []): int
    {
        $db = Database::connection();
        [$from, $params] = $this->buildProductFrom($search, $statuses);
        $stmt = $db->prepare("SELECT COUNT(*) {$from}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // Shared FROM/JOIN/WHERE for all() and count() so the list and its total agree.
    private function buildProductFrom(?string $search, array $statuses): array
    {
        $sql = "FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id
                WHERE 1=1";

        $params = [];
        if ($search !== null && $search !== '') {
            $sql .= " AND (p.product_name LIKE ? OR p.sku LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $validStatuses = array_values(array_intersect($statuses, self::$statuses));
        if (!empty($validStatuses)) {
            $statusClauses = [];
            foreach ($validStatuses as $status) {
                if ($status === 'No Stock') {
                    $statusClauses[] = 'COALESCE(i.available_quantity, 0) = 0';
                } elseif ($status === 'Low Stock') {
                    $statusClauses[] = '(i.available_quantity > 0 AND i.available_quantity < i.low_stock_threshold)';
                } elseif ($status === 'In Stock') {
                    $statusClauses[] = 'i.available_quantity >= i.low_stock_threshold';
                }
            }
            $sql .= ' AND (' . implode(' OR ', $statusClauses) . ')';
        }

        return [$sql, $params];
    }

    /**
     * Find a single product (with inventory) by product id.
     */
    public function findById(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT p.id, p.product_name, p.sku, p.price, p.description,
                    p.created_at, p.updated_at,
                    i.id AS inventory_id, i.available_quantity, i.reserved_quantity, i.low_stock_threshold
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find a product by exact SKU match, or null if no product uses it.
     * Used to block duplicate SKUs when creating/editing products.
     */
    public function findBySku(string $sku): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT id, product_name, sku FROM products WHERE sku = ? LIMIT 1");
        $stmt->execute([$sku]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Insert a new product and its starting inventory row.
     */
    public function create(string $productName, string $sku, float $price, ?string $description, int $availableQuantity, int $lowStockThreshold): int
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "INSERT INTO products (product_name, sku, price, description)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$productName, $sku, $price, $description]);
        $productId = (int) $db->lastInsertId();

        $stmt = $db->prepare(
            "INSERT INTO inventory (product_id, available_quantity, reserved_quantity, low_stock_threshold)
             VALUES (?, ?, 0, ?)"
        );
        $stmt->execute([$productId, $availableQuantity, $lowStockThreshold]);

        return $productId;
    }

    /**
     * Update product details (name, sku, price, description).
     */
    public function updateProduct(int $id, string $productName, string $sku, float $price, ?string $description): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE products
             SET product_name = ?, sku = ?, price = ?, description = ?
             WHERE id = ?"
        );
        return $stmt->execute([$productName, $sku, $price, $description, $id]);
    }

    /**
     * Update just the low-stock threshold for a product's inventory row.
     */
    public function updateLowStockThreshold(int $productId, int $lowStockThreshold): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE inventory SET low_stock_threshold = ? WHERE product_id = ?"
        );
        return $stmt->execute([$lowStockThreshold, $productId]);
    }

    /**
     * Update available/reserved stock quantities for a product.
     * Reserved is driven only by the RFQ reservation flow, so this low-level
     * setter is intended for reservation sync (reserve/release/convert), not
     * for manual edits. Manual stock edits should use updateAvailableQuantity().
     */
    public function updateStock(int $productId, int $availableQuantity, int $reservedQuantity): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE inventory
             SET available_quantity = ?, reserved_quantity = ?
             WHERE product_id = ?"
        );
        return $stmt->execute([$availableQuantity, $reservedQuantity, $productId]);
    }

    /**
     * Update only the available quantity for a product, leaving reserved_quantity
     * untouched. Reserved is owned by the RFQ reservation flow and must never be
     * set by hand from the stock form.
     */
    public function updateAvailableQuantity(int $productId, int $availableQuantity): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE inventory SET available_quantity = ? WHERE product_id = ?"
        );
        return $stmt->execute([$availableQuantity, $productId]);
    }

    /**
     * Get all reservations, joined with product and RFQ info.
     */
    public function allReservations(): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT r.id, r.rfq_id, r.product_id, r.quantity_reserved, r.reservation_status,
                    r.created_at, p.product_name, p.sku, rfq.title AS rfq_title,
                    i.available_quantity
             FROM rfq_inventory_reservations r
             JOIN products p ON p.id = r.product_id
             JOIN rfqs rfq ON rfq.id = r.rfq_id
             LEFT JOIN inventory i ON i.product_id = r.product_id
             ORDER BY r.created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a single reservation by id.
     */
    public function findReservationById(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT r.*, p.product_name, p.sku
             FROM rfq_inventory_reservations r
             JOIN products p ON p.id = r.product_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create a new reservation row tied to an RFQ and product.
     */
    public function createReservation(int $rfqId, int $productId, int $quantity): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO rfq_inventory_reservations (rfq_id, product_id, quantity_reserved, reservation_status)
             VALUES (?, ?, ?, 'Reserved')"
        );
        $stmt->execute([$rfqId, $productId, $quantity]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update a reservation's status (Reserved / Released / Converted).
     */
    public function updateReservationStatus(int $id, string $status): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE rfq_inventory_reservations SET reservation_status = ? WHERE id = ?"
        );
        return $stmt->execute([$status, $id]);
    }

    /**
     * Delete a product and its inventory row.
     * The inventory row is removed automatically via ON DELETE CASCADE.
     * Will fail if active reservations exist (FK constraint on rfq_inventory_reservations).
     */
    public function delete(int $id): bool
    {
        $db   = Database::connection();
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count active (Reserved) reservations for a product.
     * Used to block deletion when stock is committed to an RFQ.
     */
    public function countActiveReservations(int $productId): int
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM rfq_inventory_reservations
             WHERE product_id = ? AND reservation_status = 'Reserved'"
        );
        $stmt->execute([$productId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count reservations of every status for a product.
     *
     * The foreign key from rfq_inventory_reservations is RESTRICT, so a
     * Released or Converted row blocks a DELETE exactly as an active one does.
     * deleteProduct() needs this to give an accurate reason instead of letting
     * the database raise an unhandled integrity error.
     */
    public function countReservations(int $productId): int
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM rfq_inventory_reservations WHERE product_id = ?"
        );
        $stmt->execute([$productId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Inventory Ledger ──────────────────────────────────────────────────────
    // Append-only audit trail (migrations/015_create_inventory_movements.sql)
    // backing the printable Inventory Ledger report: what happened to a product,
    // when, and who did it.

    /** Matches the movement_type ENUM in migrations/015_create_inventory_movements.sql. */
    public static array $movementTypes = [
        'created', 'updated', 'manual_adjustment', 'reserved', 'released', 'converted', 'deleted',
    ];

    /**
     * Append an immutable ledger row for a product-affecting event. Snapshots
     * the product name/SKU and acting user's name at write time so the ledger
     * stays meaningful even after the product is deleted or the user account
     * removed (both FKs are ON DELETE SET NULL, not CASCADE).
     *
     * Uses Database::connection() directly (no transaction of its own), so a
     * caller that's already inside a transaction — e.g. the RFQ reservation
     * flow — gets this write folded into that same transaction/rollback.
     *
     * Must be called *before* deleting a product (movement_type 'deleted'):
     * afterward there is no product row left to snapshot from.
     */
    public function logMovement(int $productId, ?int $userId, string $movementType, ?int $quantityDelta, ?string $note = null): void
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT p.product_name, p.sku, i.available_quantity, i.reserved_quantity
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.id = ?"
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if ($product === false) {
            return;
        }

        $userName = null;
        if ($userId !== null) {
            $userStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userName = $userStmt->fetchColumn() ?: null;
        }

        $insert = $db->prepare(
            "INSERT INTO inventory_movements
                (product_id, product_name, sku, user_id, user_name, movement_type,
                 quantity_delta, available_quantity_after, reserved_quantity_after, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $productId,
            $product['product_name'],
            $product['sku'],
            $userId,
            $userName,
            $movementType,
            $quantityDelta,
            $product['available_quantity'] !== null ? (int) $product['available_quantity'] : null,
            $product['reserved_quantity']  !== null ? (int) $product['reserved_quantity']  : null,
            $note,
        ]);
    }

    // Total ledger rows matching the given filters (for pagination).
    public function movementsCount(?int $productId, string $search, array $movementTypes): int
    {
        [$where, $params] = $this->buildMovementsWhere($productId, $search, $movementTypes);
        $db   = Database::connection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM inventory_movements m{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Paged, sorted, filtered ledger rows for the report view/print page.
     * $sortCol is resolved through an allowlist so it's safe to pass ORDER BY
     * straight from a request parameter.
     */
    public function movements(?int $productId, string $search, array $movementTypes, string $sortCol, string $sortDir, int $limit, int $offset): array
    {
        $colMap = [
            'created_at'     => 'm.created_at',
            'product_name'   => 'm.product_name',
            'sku'            => 'm.sku',
            'user_name'      => 'm.user_name',
            'movement_type'  => 'm.movement_type',
            'quantity_delta' => 'm.quantity_delta',
        ];
        $orderCol = $colMap[$sortCol] ?? 'm.created_at';
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildMovementsWhere($productId, $search, $movementTypes);
        $limit  = max(1, $limit);   // guard the interpolated LIMIT/OFFSET
        $offset = max(0, $offset);

        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT m.id, m.product_id, m.product_name, m.sku, m.user_id, m.user_name,
                    m.movement_type, m.quantity_delta, m.available_quantity_after,
                    m.reserved_quantity_after, m.note, m.created_at
             FROM inventory_movements m
             {$where}
             ORDER BY {$orderCol} {$orderDir}
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Shared WHERE for movements()/movementsCount() so the list and its total agree.
    private function buildMovementsWhere(?int $productId, string $search, array $movementTypes): array
    {
        $clauses = [];
        $params  = [];

        if ($productId !== null) {
            $clauses[] = 'm.product_id = ?';
            $params[]  = $productId;
        }
        if ($search !== '') {
            $clauses[] = '(m.product_name LIKE ? OR m.sku LIKE ? OR m.user_name LIKE ? OR m.note LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $validTypes = array_values(array_intersect($movementTypes, self::$movementTypes));
        if (!empty($validTypes)) {
            $placeholders = implode(',', array_fill(0, count($validTypes), '?'));
            $clauses[]    = "m.movement_type IN ({$placeholders})";
            array_push($params, ...$validTypes);
        }

        $sql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        return [$sql, $params];
    }
}
