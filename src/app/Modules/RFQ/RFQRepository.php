<?php
namespace App\Modules\RFQ;

use App\Core\Database;
use App\Core\DataTable\ServerTable;
use PDO;

class RFQRepository
{
    public static array $stages = ['New', 'In Review', 'Quoted', 'Negotiation', 'Won', 'Lost'];

    /**
     * Server-side DataTables source for the RFQ list. Shared by the data
     * endpoint (paged JSON) and the export endpoint (full filtered set) so their
     * filter/sort semantics can never diverge. Text search on title/account uses
     * the FULLTEXT indexes (ft_rfqs_title, ft_accounts_name); stage is an exact
     * per-column select filter.
     */
    public static function listTable(): ServerTable
    {
        return new ServerTable(
            Database::connection(),
            'rfqs r LEFT JOIN accounts a ON a.id = r.account_id',
            'r.id, r.title, a.account_name AS account_name, r.stage, r.created_at, r.updated_at',
            [
                ['data' => 'id',           'sql' => 'r.id',           'order' => true, 'search' => 'like'],
                ['data' => 'title',        'sql' => 'r.title',        'order' => true, 'search' => 'fulltext', 'ft' => 'r.title'],
                ['data' => 'account_name', 'sql' => 'a.account_name', 'order' => true, 'search' => 'fulltext', 'ft' => 'a.account_name'],
                ['data' => 'stage',        'sql' => 'r.stage',        'order' => true, 'search' => 'exact'],
                ['data' => 'created_at',   'sql' => 'r.created_at',   'order' => true, 'search' => false],
                ['data' => 'updated_at',   'sql' => 'r.updated_at',   'order' => true, 'search' => false],
            ],
            'r.created_at',
            'DESC'
        );
    }

    /** How long (seconds) cached analytics results stay fresh. */
    private const ANALYTICS_TTL = 300;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    // ── Board ─────────────────────────────────────────────────────────────────

    // NOTE: the pipeline view no longer renders a grouped board, so this is not
    // currently called. Kept as a *bounded* helper so any future board reuse
    // cannot silently load the entire table — callers pass an explicit cap.
    public function allForBoard(int $limit = 200): array
    {
        $limit = max(1, $limit); // guard the interpolated LIMIT
        $stmt  = $this->db->prepare("
            SELECT id, title, stage, created_at
            FROM rfqs
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── List / Search ─────────────────────────────────────────────────────────

    public function searchCount(string $q = '', string $idSearch = '', array $stages = []): int
    {
        [$where, $params, $joinAccounts] = $this->buildWhere($q, $idSearch, $stages);
        // Only join accounts when the WHERE actually references it (text search).
        $join = $joinAccounts ? ' LEFT JOIN accounts a ON a.id = r.account_id' : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rfqs r{$join}{$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function search(
        string $q        = '',
        string $sortCol  = 'created_at',
        string $sortDir  = 'DESC',
        int    $limit    = 25,
        int    $offset   = 0,
        string $idSearch = '',
        array  $stages   = []
    ): array {
        $colMap = [
            'id'           => 'r.id',
            'title'        => 'r.title',
            'account_name' => 'a.account_name',
            'stage'        => 'r.stage',
            'created_at'   => 'r.created_at',
            'updated_at'   => 'r.updated_at',
        ];
        $orderCol = $colMap[$sortCol] ?? 'r.created_at';
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildWhere($q, $idSearch, $stages);

        $stmt = $this->db->prepare("
            SELECT r.id, r.title, a.account_name, r.stage, r.created_at, r.updated_at
            FROM rfqs r
            LEFT JOIN accounts a ON a.id = r.account_id
            {$where}
            ORDER BY {$orderCol} {$orderDir} LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Lookups ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.id, r.title, r.description, r.stage, r.created_at, r.updated_at,
                a.id            AS account_id,
                a.account_name, a.email AS account_email, a.phone AS account_phone,
                c.id            AS contact_id,
                c.account_id    AS contact_account_id,
                CONCAT(c.first_name, ' ', c.last_name) AS contact_name,
                c.email         AS contact_email,
                c.phone         AS contact_phone,
                c.title         AS contact_title,
                u.name          AS created_by_name
            FROM rfqs r
            LEFT JOIN accounts a ON a.id = r.account_id
            LEFT JOIN contacts c ON c.id = r.contact_id
            LEFT JOIN users   u ON u.id = r.created_by_user_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allAccounts(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, account_name FROM accounts ORDER BY account_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allContacts(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, account_id, first_name, last_name, title
             FROM contacts ORDER BY last_name ASC, first_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allProducts(): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.product_name, p.sku, p.price, p.description,
                   COALESCE(i.available_quantity, 0) AS available_quantity
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id
            ORDER BY p.product_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Autocomplete (bounded search for form pickers) ────────────────────────
    // These back the server-side autocomplete endpoints so the create/edit forms
    // no longer embed the entire accounts/contacts/products tables in the page.
    // Each accepts either a search term ($q) or a single id lookup (to resolve the
    // label of an already-selected value), and always caps its result set.

    public function searchAccounts(string $q = '', ?int $id = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50)); // guard the interpolated LIMIT
        if ($id !== null) {
            $stmt = $this->db->prepare("SELECT id, account_name FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($q !== '') {
            $stmt = $this->db->prepare(
                "SELECT id, account_name FROM accounts WHERE account_name LIKE ? ORDER BY account_name ASC LIMIT {$limit}"
            );
            $stmt->execute(["%{$q}%"]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id, account_name FROM accounts ORDER BY account_name ASC LIMIT {$limit}"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function searchContacts(string $q = '', ?int $accountId = null, ?int $id = null, int $limit = 20): array
    {
        $limit  = max(1, min($limit, 50));
        $where  = [];
        $params = [];

        if ($id !== null) {
            $where[]  = 'id = ?';
            $params[] = $id;
        } else {
            if ($accountId !== null) {
                $where[]  = 'account_id = ?';
                $params[] = $accountId;
            }
            if ($q !== '') {
                $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ?)';
                $params[] = "%{$q}%";
                $params[] = "%{$q}%";
                $params[] = "%{$q}%";
            }
        }

        $sql = "SELECT id, account_id, first_name, last_name, title FROM contacts";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY last_name ASC, first_name ASC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function searchProducts(string $q = '', ?int $id = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $base  = "SELECT p.id, p.product_name, p.sku, p.price,
                         COALESCE(i.available_quantity, 0) AS available_quantity
                  FROM products p
                  LEFT JOIN inventory i ON i.product_id = p.id";

        if ($id !== null) {
            $stmt = $this->db->prepare("{$base} WHERE p.id = ?");
            $stmt->execute([$id]);
        } elseif ($q !== '') {
            $stmt = $this->db->prepare(
                "{$base} WHERE p.product_name LIKE ? OR p.sku LIKE ? ORDER BY p.product_name ASC LIMIT {$limit}"
            );
            $stmt->execute(["%{$q}%", "%{$q}%"]);
        } else {
            $stmt = $this->db->prepare("{$base} ORDER BY p.product_name ASC LIMIT {$limit}");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    // ── Quote / Reservation Reads ─────────────────────────────────────────────

    public function findQuoteById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, rfq_id, quote_amount, discount, validity_start_date, validity_end_date FROM quotes WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getQuotesByRfqId(int $rfqId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id, quote_amount, discount, validity_start_date, validity_end_date, created_at,
                DATEDIFF(validity_end_date, CURDATE()) AS days_remaining
            FROM quotes
            WHERE rfq_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$rfqId]);
        return $stmt->fetchAll();
    }

    public function getReservationsByRfqId(int $rfqId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                res.id, res.product_id, res.quantity_reserved, res.reservation_status, res.created_at,
                p.product_name, p.sku, p.price
            FROM rfq_inventory_reservations res
            JOIN products p ON p.id = res.product_id
            WHERE res.rfq_id = ?
            ORDER BY res.created_at DESC
        ");
        $stmt->execute([$rfqId]);
        return $stmt->fetchAll();
    }

    // ── Writes ────────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO rfqs (account_id, contact_id, created_by_user_id, title, description, stage)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['account_id'] !== null ? (int)$data['account_id'] : null,
            $data['contact_id'] !== null ? (int)$data['contact_id'] : null,
            (int)$data['created_by_user_id'],
            $data['title'],
            $data['description'],
            $data['stage'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE rfqs
            SET title = ?, account_id = ?, contact_id = ?, description = ?, stage = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['account_id'] !== null ? (int)$data['account_id'] : null,
            $data['contact_id'] !== null ? (int)$data['contact_id'] : null,
            $data['description'],
            $data['stage'],
            $id,
        ]);
    }

    public function updateStage(int $id, string $stage): void
    {
        $stmt = $this->db->prepare("UPDATE rfqs SET stage = ? WHERE id = ?");
        $stmt->execute([$stage, $id]);
    }

    public function insertQuote(int $rfqId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO quotes (rfq_id, quote_amount, discount, validity_start_date, validity_end_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $rfqId,
            (float)$data['quote_amount'],
            isset($data['discount']) && $data['discount'] !== '' ? (float)$data['discount'] : 0,
            $data['validity_start_date'] !== '' ? $data['validity_start_date'] : null,
            $data['validity_end_date']   !== '' ? $data['validity_end_date']   : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function insertReservation(int $rfqId, array $data): int
    {
        $qty       = (int)$data['quantity_reserved'];
        $productId = (int)$data['product_id'];

        $this->db->beginTransaction();
        try {
            // Lock this product's inventory row for the duration of the
            // transaction so concurrent reservations serialize instead of racing
            // on available_quantity / reserved_quantity.
            $lock = $this->db->prepare(
                "SELECT available_quantity FROM inventory WHERE product_id = ? FOR UPDATE"
            );
            $lock->execute([$productId]);
            $available = $lock->fetchColumn();

            // The lock alone only serialized the races — it never rejected them,
            // so an over-sized reservation drove available_quantity negative.
            if ($available === false) {
                throw new \DomainException('No inventory record exists for this product.');
            }
            if ($qty < 1) {
                throw new \DomainException('Reservation quantity must be at least 1.');
            }
            if ((int)$available < $qty) {
                throw new \DomainException(
                    "Insufficient inventory: {$available} available, {$qty} requested."
                );
            }

            $stmt = $this->db->prepare("
                INSERT INTO rfq_inventory_reservations (rfq_id, product_id, quantity_reserved, reservation_status)
                VALUES (?, ?, ?, 'Reserved')
            ");
            $stmt->execute([$rfqId, $productId, $qty]);
            $reservationId = (int)$this->db->lastInsertId();

            $this->db->prepare("
                UPDATE inventory
                SET available_quantity = available_quantity - ?,
                    reserved_quantity  = reserved_quantity  + ?
                WHERE product_id = ?
            ")->execute([$qty, $qty, $productId]);

            // Record it in the ledger. RFQ-driven stock movements used to be
            // invisible there, so the audit trail disagreed with the stock it
            // was supposed to explain. Inside the transaction, so a rollback
            // discards the ledger row too.
            $this->logInventoryMovement($productId, 'reserved', -$qty, "Reserved for RFQ #{$rfqId}.");

            $this->db->commit();
            return $reservationId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Restores inventory for any still-Reserved reservations before cascade-deleting the RFQ.
    public function delete(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE inventory i
                JOIN rfq_inventory_reservations res ON res.product_id = i.product_id
                SET i.available_quantity = i.available_quantity + res.quantity_reserved,
                    i.reserved_quantity  = i.reserved_quantity  - res.quantity_reserved
                WHERE res.rfq_id = ? AND res.reservation_status = 'Reserved'
            ")->execute([$id]);

            $this->db->prepare("DELETE FROM rfqs WHERE id = ?")->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateQuote(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE quotes SET quote_amount = ?, discount = ?, validity_start_date = ?, validity_end_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (float)$data['quote_amount'],
            isset($data['discount']) && $data['discount'] !== '' ? (float)$data['discount'] : 0,
            $data['validity_start_date'] !== '' ? $data['validity_start_date'] : null,
            $data['validity_end_date']   !== '' ? $data['validity_end_date']   : null,
            $id,
        ]);
    }

    public function deleteQuote(int $id): void
    {
        $this->db->prepare("DELETE FROM quotes WHERE id = ?")->execute([$id]);
    }

    // Adjusts inventory counters based on status transition, then updates the row.
    // Reserved → Released: return stock to available.
    // Reserved → Converted: remove from reserved (product consumed/shipped).
    // Terminal states (Released, Converted) cannot transition further.
    public function updateReservationStatus(int $id, string $status): void
    {
        $valid = ['Reserved', 'Released', 'Converted'];
        if (!in_array($status, $valid, true)) return;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT product_id, quantity_reserved, reservation_status FROM rfq_inventory_reservations WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if (!$row || $row['reservation_status'] !== 'Reserved') {
                $this->db->rollBack();
                return;
            }

            $qty       = (int)$row['quantity_reserved'];
            $productId = (int)$row['product_id'];

            if ($status === 'Released') {
                $this->db->prepare("
                    UPDATE inventory
                    SET available_quantity = available_quantity + ?,
                        reserved_quantity  = reserved_quantity  - ?
                    WHERE product_id = ?
                ")->execute([$qty, $qty, $productId]);
            } elseif ($status === 'Converted') {
                $this->db->prepare("
                    UPDATE inventory SET reserved_quantity = reserved_quantity - ?
                    WHERE product_id = ?
                ")->execute([$qty, $productId]);
            }

            $this->db->prepare(
                "UPDATE rfq_inventory_reservations SET reservation_status = ? WHERE id = ?"
            )->execute([$status, $id]);

            // Released returns stock to available; Converted consumes it. Both
            // change inventory, so both belong in the ledger.
            $this->logInventoryMovement(
                $productId,
                $status === 'Released' ? 'released' : 'converted',
                $status === 'Released' ? $qty : 0,
                ($status === 'Released' ? 'Released from' : 'Converted for') . " reservation #{$id}."
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findReservationById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                res.id, res.rfq_id, res.product_id, res.quantity_reserved, res.reservation_status,
                p.product_name, p.sku, p.price,
                COALESCE(i.available_quantity, 0) AS available_quantity
            FROM rfq_inventory_reservations res
            JOIN products p ON p.id = res.product_id
            LEFT JOIN inventory i ON i.product_id = res.product_id
            WHERE res.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // If status is Reserved, returns stock to available_quantity before deleting.
    public function deleteReservation(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT product_id, quantity_reserved, reservation_status FROM rfq_inventory_reservations WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);
            $res = $stmt->fetch();

            if ($res && $res['reservation_status'] === 'Reserved') {
                $this->db->prepare("
                    UPDATE inventory
                    SET available_quantity = available_quantity + ?,
                        reserved_quantity  = reserved_quantity  - ?
                    WHERE product_id = ?
                ")->execute([$res['quantity_reserved'], $res['quantity_reserved'], $res['product_id']]);
            }

            $this->db->prepare("DELETE FROM rfq_inventory_reservations WHERE id = ?")->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Adjusts inventory by the quantity delta (only for Reserved status).
    public function updateReservation(int $id, int $newQty): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT product_id, quantity_reserved, reservation_status FROM rfq_inventory_reservations WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);
            $res = $stmt->fetch();

            if ($newQty < 1) {
                throw new \DomainException('Reservation quantity must be at least 1.');
            }

            if ($res && $res['reservation_status'] === 'Reserved') {
                $diff = $newQty - (int)$res['quantity_reserved'];
                if ($diff !== 0) {
                    // Lock inventory too: the reservation-row lock above does not
                    // stop two reservations of the same product from racing here.
                    $lock = $this->db->prepare(
                        "SELECT available_quantity FROM inventory WHERE product_id = ? FOR UPDATE"
                    );
                    $lock->execute([$res['product_id']]);
                    $available = $lock->fetchColumn();

                    // Only an increase consumes stock; a decrease returns it.
                    if ($diff > 0 && (int)$available < $diff) {
                        throw new \DomainException(
                            "Insufficient inventory: {$available} available, {$diff} more requested."
                        );
                    }

                    $this->db->prepare("
                        UPDATE inventory
                        SET available_quantity = available_quantity - ?,
                            reserved_quantity  = reserved_quantity  + ?
                        WHERE product_id = ?
                    ")->execute([$diff, $diff, $res['product_id']]);
                }
            }

            $this->db->prepare(
                "UPDATE rfq_inventory_reservations SET quantity_reserved = ? WHERE id = ?"
            )->execute([$newQty, $id]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    /**
     * Append an inventory_movements row for an RFQ-driven stock change.
     *
     * Duplicates InventoryRepository::logMovement rather than calling it so the
     * write joins the caller's open transaction on this same connection. The
     * ledger is append-only and read by the Inventory module's ledger view.
     */
    private function logInventoryMovement(int $productId, string $movementType, ?int $quantityDelta, string $note): void
    {
        $userId = $_SESSION['user']['id'] ?? null;

        $stmt = $this->db->prepare(
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
            $u = $this->db->prepare("SELECT name FROM users WHERE id = ?");
            $u->execute([$userId]);
            $userName = $u->fetchColumn() ?: null;
        }

        $this->db->prepare(
            "INSERT INTO inventory_movements
                (product_id, product_name, sku, user_id, user_name, movement_type,
                 quantity_delta, available_quantity_after, reserved_quantity_after, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $productId,
            $product['product_name'],
            $product['sku'],
            $userId,
            $userName,
            $movementType,
            $quantityDelta,
            $product['available_quantity'] !== null ? (int)$product['available_quantity'] : null,
            $product['reserved_quantity']  !== null ? (int)$product['reserved_quantity']  : null,
            $note,
        ]);
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    /**
     * Win/loss performance per account, most successful first.
     *
     * Bounded for dashboard + drill-down use: pass $limit (and $offset for
     * pagination) so the caller never loads one row per account. "closed" counts
     * terminal RFQs (Won + Lost); the win rate is the documented business rule
     *
     *     won / (won + lost) * 100
     *
     * so still-open RFQs never dilute the denominator. Accounts with no closed
     * RFQs yet report a NULL win_rate_pct (MySQL sorts these last under DESC,
     * and the view renders them as "—").
     *
     * Grouping/aggregation happen in the database; only the requested page of
     * rows crosses into PHP.
     */
    public function winRateByAccount(int $limit = 5, int $offset = 0): array
    {
        $limit  = max(1, $limit);   // guard the interpolated LIMIT/OFFSET
        $offset = max(0, $offset);
        $stmt = $this->db->prepare("
            SELECT
                a.account_name,
                COUNT(r.id)                              AS total_rfqs,
                SUM(r.stage = 'Won')                     AS won,
                SUM(r.stage = 'Lost')                    AS lost,
                SUM(r.stage IN ('Won','Lost'))           AS closed,
                ROUND(
                    SUM(r.stage = 'Won')
                    / NULLIF(SUM(r.stage IN ('Won','Lost')), 0) * 100
                , 1)                                     AS win_rate_pct
            FROM rfqs r
            JOIN accounts a ON a.id = r.account_id
            GROUP BY a.id, a.account_name
            ORDER BY win_rate_pct DESC, total_rfqs DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** How many accounts have at least one RFQ — the population winRateByAccount() pages over. */
    public function winRateByAccountCount(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT r.account_id) FROM rfqs r WHERE r.account_id IS NOT NULL"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Aggregate count + total quoted value for RFQs in the given stages, computed
     * entirely in SQL. Backs the "Active RFQs" dashboard card (Quoted +
     * Negotiation) so it shows totals without ever loading individual RFQs.
     * Value follows the same convention as totalValueByStage(): the sum of every
     * attached quote's amount.
     *
     * @param string[] $stages
     * @return array{count:int,total_value:float}
     */
    public function stageSummary(array $stages): array
    {
        $stages = array_values(array_intersect($stages, self::$stages));
        if (empty($stages)) {
            return ['count' => 0, 'total_value' => 0.0];
        }
        $ph   = implode(',', array_fill(0, count($stages), '?'));
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT r.id)             AS cnt,
                COALESCE(SUM(q.quote_amount), 0) AS total_value
            FROM rfqs r
            LEFT JOIN quotes q ON q.rfq_id = r.id
            WHERE r.stage IN ({$ph})
        ");
        $stmt->execute($stages);
        $row = $stmt->fetch() ?: [];
        return [
            'count'       => (int)($row['cnt'] ?? 0),
            'total_value' => (float)($row['total_value'] ?? 0),
        ];
    }

    /**
     * The most recently updated RFQs (capped at $limit), each with its account,
     * stage, and total quoted value — for the "Recent RFQs" dashboard card.
     *
     * A derived table selects the top-$limit RFQs by updated_at first (using
     * idx_rfqs_updated_at), so the quote aggregation only runs over those few
     * rows rather than the whole table.
     */
    public function recentRfqs(int $limit = 5): array
    {
        $limit = max(1, min($limit, 50)); // guard the interpolated LIMIT
        $stmt  = $this->db->prepare("
            SELECT
                r.id, r.title, r.stage, r.created_at, r.updated_at,
                a.account_name,
                COALESCE(SUM(q.quote_amount), 0) AS total_value
            FROM (
                SELECT id, title, stage, created_at, updated_at, account_id
                FROM rfqs
                ORDER BY updated_at DESC
                LIMIT {$limit}
            ) r
            LEFT JOIN accounts a ON a.id = r.account_id
            LEFT JOIN quotes   q ON q.rfq_id = r.id
            GROUP BY r.id, r.title, r.stage, r.created_at, r.updated_at, a.account_name
            ORDER BY r.updated_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalValueByStage(): array
    {
        return $this->cached('total_value_by_stage', function (): array {
            $stmt = $this->db->prepare("
                SELECT
                    r.stage,
                    COUNT(DISTINCT r.id)               AS rfq_count,
                    COALESCE(SUM(q.quote_amount), 0)   AS total_value,
                    COALESCE(AVG(q.quote_amount), 0)   AS avg_value
                FROM rfqs r
                LEFT JOIN quotes q ON q.rfq_id = r.id
                GROUP BY r.stage
                ORDER BY FIELD(r.stage, 'New', 'In Review', 'Quoted', 'Negotiation', 'Won', 'Lost')
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        });
    }

    public function quotesExpiringSoon(int $limit = 5): array
    {
        $limit = max(1, min($limit, 50)); // guard the interpolated LIMIT
        return $this->cached("quotes_expiring_soon_{$limit}", function () use ($limit): array {
            // Bounded: this is an alerts widget, not a full report. Ordered so the
            // most-overdue/soonest-expiring surface first; overdue rows are kept
            // (the view renders them as "Nd overdue"), just no longer unbounded.
            $stmt = $this->db->prepare("
                SELECT
                    r.id, r.title, r.stage,
                    a.account_name,
                    q.quote_amount, q.validity_end_date,
                    DATEDIFF(q.validity_end_date, CURDATE()) AS days_remaining
                FROM rfqs r
                JOIN quotes   q ON q.rfq_id = r.id
                JOIN accounts a ON a.id     = r.account_id
                WHERE r.stage IN ('Quoted', 'Negotiation')
                ORDER BY q.validity_end_date ASC
                LIMIT {$limit}
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        });
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Return a cached result for $key, else run $producer, cache it, and return.
     * Backed by a JSON file under storage/cache with a short TTL: analytics
     * dashboards tolerate slightly stale numbers, and this spares the DB a set
     * of full-table aggregates on every page load. Cache read/write failures
     * fall back to a live query, so a missing/unwritable cache dir is harmless.
     *
     * @param callable():array $producer
     */
    private function cached(string $key, callable $producer): array
    {
        $dir  = __DIR__ . '/../../../storage/cache';
        $file = $dir . '/rfq_' . preg_replace('/[^a-z0-9_]/i', '', $key) . '.json';

        if (is_file($file) && (time() - (int) filemtime($file)) < self::ANALYTICS_TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $data = $producer();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data;
    }

    private function buildWhere(string $q, string $idSearch, array $stages): array
    {
        $clauses = [];
        $params  = [];

        $joinAccounts = false;

        if ($idSearch !== '') {
            $clauses[] = 'r.id = ?';
            $params[]  = (int)$idSearch;
        }
        if ($q !== '') {
            // The text search reaches into accounts.account_name, so the caller
            // must join accounts for this clause to resolve.
            $joinAccounts = true;
            $boolean = $this->toBooleanQuery($q);
            if ($boolean !== '') {
                // Fast path: FULLTEXT prefix match, backed by the ft_rfqs_title
                // and ft_accounts_name indexes (see migration 013). No table scan.
                $clauses[] = '(MATCH(r.title) AGAINST (? IN BOOLEAN MODE)'
                           . ' OR MATCH(a.account_name) AGAINST (? IN BOOLEAN MODE))';
                $params[]  = $boolean;
                $params[]  = $boolean;
            } else {
                // Fallback for queries below the FULLTEXT minimum token size
                // (e.g. 1–2 characters): a plain LIKE, run rarely.
                $clauses[] = '(r.title LIKE ? OR a.account_name LIKE ?)';
                $params[]  = "%{$q}%";
                $params[]  = "%{$q}%";
            }
        }
        $validStages = array_values(array_intersect($stages, self::$stages));
        if (!empty($validStages)) {
            $placeholders = implode(',', array_fill(0, count($validStages), '?'));
            $clauses[]    = "r.stage IN ({$placeholders})";
            array_push($params, ...$validStages);
        }

        $sql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        return [$sql, $params, $joinAccounts];
    }

    /**
     * Turn a search-box string into a safe FULLTEXT BOOLEAN-mode query:
     *   "acme widget"  ->  "+acme* +widget*"   (each word required, prefix match)
     *
     * BOOLEAN operators are stripped so user input can't cause a syntax error,
     * and words shorter than the InnoDB minimum token size (3) are dropped. If
     * nothing usable remains, returns '' and the caller falls back to LIKE.
     */
    private function toBooleanQuery(string $q): string
    {
        $clean = preg_replace('/[+\-><()~*"@]+/', ' ', $q);
        $words = preg_split('/\s+/', trim((string)$clean), -1, PREG_SPLIT_NO_EMPTY);

        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3) {
                $terms[] = '+' . $word . '*';
            }
        }
        return implode(' ', $terms);
    }
}
