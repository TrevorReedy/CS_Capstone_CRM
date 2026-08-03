<?php
namespace App\Modules\Inventory;

// HTTP-facing entry points for the Inventory module (product CRUD, stock
// updates, and reservation actions). Routed from public/modules/inventory/products.php.
class InventoryController
{
    private InventoryService $service;

    public function __construct()
    {
        $this->service = new InventoryService();
    }

    /**
     * GET /modules/inventory/products.php
     * Inventory List page — supports ?search= and ?low_stock=1
     */
    public function index(): void
    {
        // The products list is now a client-driven DataTable (server-side
        // processing). This just renders the shell; rows are fetched from
        // /modules/inventory/products_data.php.
        include __DIR__ . '/views/products_list.php';
    }

    /**
     * GET /modules/inventory/product_detail.php?id=X
     * GET /modules/inventory/product_detail.php  (no id = "add new" mode)
     */
    public function show(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $product = $id !== null ? $this->service->getProductDetail($id) : null;
        $error = null;

        include __DIR__ . '/views/product_detail.php';
    }

    /**
     * POST /modules/inventory/product_detail.php
     * Handles both create (no id) and update (id present).
     */
    public function save(): void
    {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $productName = trim($_POST['product_name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '') ?: null;
        $startingQuantity = (int) ($_POST['available_quantity'] ?? 0);
        $lowStockThreshold = (int) ($_POST['low_stock_threshold'] ?? InventoryService::DEFAULT_LOW_STOCK_THRESHOLD);

        $userId = (int) (\App\Core\Auth::user()['id'] ?? 0) ?: null;

        try {
            if ($id === null) {
                $newId = $this->service->createProduct($productName, $sku, $price, $description, $startingQuantity, $lowStockThreshold, $userId);
                $_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$productName}\" was created successfully."];
                header('Location: /modules/inventory/products.php?page=detail&id=' . $newId);
                exit;
            }

            $this->service->updateProduct($id, $productName, $sku, $price, $description, $lowStockThreshold, $userId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$productName}\" was saved successfully."];
            header('Location: /modules/inventory/products.php?page=detail&id=' . $id);
            exit;
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            $back = $id !== null
                ? '/modules/inventory/products.php?page=detail&id=' . $id
                : '/modules/inventory/products.php?page=detail';
            header('Location: ' . $back);
            exit;
        } catch (\PDOException $e) {
            // Safety net: InventoryService::createProduct()/updateProduct() already
            // check for a duplicate SKU up front, but if a unique-constraint violation
            // (SQLSTATE 23000) reaches the database anyway — e.g. two submits at once —
            // show the same friendly, dismissible error instead of a raw 500.
            $message = $e->getCode() === '23000'
                ? 'That SKU is already in use by another product.'
                : 'Something went wrong while saving this product.';
            $_SESSION['flash'] = ['type' => 'error', 'message' => $message];
            $back = $id !== null
                ? '/modules/inventory/products.php?page=detail&id=' . $id
                : '/modules/inventory/products.php?page=detail';
            header('Location: ' . $back);
            exit;
        }
    }

    /**
     * GET /modules/inventory/stock_update.php?id=X
     * Shows the stock update form for one product.
     */
    public function editStock(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $product = $this->service->getProductDetail($id);
        $error = null;

        include __DIR__ . '/views/stock_update.php';
    }

    /**
     * POST /modules/inventory/stock_update.php
     * Applies a new available/reserved quantity for a product.
     */
    public function updateStock(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $availableQuantity = (int) ($_POST['available_quantity'] ?? 0);
        $userId = (int) (\App\Core\Auth::user()['id'] ?? 0) ?: null;

        try {
            $this->service->updateStock($productId, $availableQuantity, $userId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stock levels updated successfully.'];
            header('Location: /modules/inventory/products.php?page=detail&id=' . $productId);
            exit;
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /modules/inventory/products.php?page=stock&id=' . $productId);
            exit;
        }
    }

    /**
     * GET ?page=delete&id=X
     * Shows a confirmation screen before deleting.
     */
    public function confirmDelete(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $product = $this->service->getProductDetail($id);

        if ($product === null) {
            http_response_code(404);
            echo '<section class="card"><h1>Product Not Found</h1><p class="text-muted">No product exists with that ID.</p><a href="/modules/inventory/products.php" class="btn mt-3">Back to Inventory</a></section>';
            return;
        }

        include __DIR__ . '/views/delete_confirm.php';
    }

    /**
     * POST ?page=delete
     * Deletes a product after checking for active reservations.
     */
    public function handleDelete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) (\App\Core\Auth::user()['id'] ?? 0) ?: null;

        try {
            $product = $this->service->getProductDetail($id);
            $name    = $product['product_name'] ?? "Product #{$id}";
            $this->service->deleteProduct($id, $userId);
            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "\"{$name}\" was deleted successfully.",
            ];
        } catch (\InvalidArgumentException | \DomainException $e) {
            // Deliberate business-rule messages ("still has active reservations").
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            // Anything else (PDO, etc.) must not reach the browser verbatim.
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => \App\Core\ErrorResponse::flash($e, 'delete the product', 'InventoryController::delete'),
            ];
        }

        header('Location: /modules/inventory/products.php');
        exit;
    }

    /**
     * GET ?page=reservations
     * Shows all RFQ inventory reservations.
     */
    public function reservations(): void
    {
        $reservations = $this->service->getReservations();

        include __DIR__ . '/views/reservations.php';
    }

    /**
     * POST /modules/inventory/reservations.php
     * Handles releasing or converting a reservation via action param.
     */
    public function updateReservation(): void
    {
        $id = (int) ($_POST['reservation_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $userId = (int) (\App\Core\Auth::user()['id'] ?? 0) ?: null;

        try {
            if ($action === 'release') {
                $this->service->releaseReservation($id, $userId);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reservation released.'];
            } elseif ($action === 'convert') {
                $this->service->convertReservation($id, $userId);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reservation converted.'];
            } else {
                throw new \InvalidArgumentException('Unknown action.');
            }
            header('Location: /modules/inventory/products.php?page=reservations');
            exit;
        } catch (\InvalidArgumentException | \DomainException $e) {
            // Deliberate business-rule messages ("Unknown action.").
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /modules/inventory/products.php?page=reservations');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash'] = [
                'type'    => 'error',
                'message' => \App\Core\ErrorResponse::flash($e, 'update the reservation', 'InventoryController::updateReservation'),
            ];
            header('Location: /modules/inventory/products.php?page=reservations');
            exit;
        }
    }

    /**
     * GET ?page=ledger
     * Inventory Ledger report: what happened to inventory, when, and who did
     * it. Supports a product/search/movement-type filter, column sorting, and
     * pagination — the same shared Paginator/pagination.php pattern used by
     * the RFQ pipeline list and the product list above.
     */
    public function ledger(): void
    {
        extract($this->fetchLedgerFilters());

        $total     = $this->service->getLedgerCount($ledgerProductId, $ledgerSearch, $ledgerTypes);
        $pager     = new \App\Core\Paginator($total, $_GET['per_page'] ?? 25, $_GET['p'] ?? 1, [10, 25, 50, 100]);
        $movements = $this->service->getLedger($ledgerProductId, $ledgerSearch, $ledgerTypes, $ledgerSort, $ledgerDir, $pager->limit(), $pager->offset());

        $movementTypes = InventoryRepository::$movementTypes;
        $product       = $ledgerProductId !== null ? $this->service->getProductDetail($ledgerProductId) : null;

        include __DIR__ . '/views/ledger.php';
    }

    /**
     * GET ?page=ledger_print
     * Bare, print-styled version of the ledger honoring the same filters as
     * the report above, but unpaginated — every matching row is included so
     * the printed/PDF'd document is a complete record. Renders its own full
     * HTML document (no shared header/sidebar/footer chrome).
     */
    public function ledgerPrint(): void
    {
        extract($this->fetchLedgerFilters());

        $movements = $this->service->getLedger($ledgerProductId, $ledgerSearch, $ledgerTypes, $ledgerSort, $ledgerDir, null, 0);
        $product   = $ledgerProductId !== null ? $this->service->getProductDetail($ledgerProductId) : null;

        include __DIR__ . '/views/ledger_print.php';
    }

    // Shared GET-parameter parsing for ledger()/ledgerPrint() so both pages
    // apply identical filters — what you see in the report is what prints.
    private function fetchLedgerFilters(): array
    {
        $ledgerProductId = isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int) $_GET['product_id'] : null;
        $ledgerSearch    = trim($_GET['q'] ?? '');
        $rawTypes        = $_GET['type'] ?? [];
        $ledgerTypes     = is_array($rawTypes) ? $rawTypes : [$rawTypes];
        $ledgerSort      = $_GET['sort'] ?? 'created_at';
        $ledgerDir       = $_GET['dir']  ?? 'DESC';

        return compact('ledgerProductId', 'ledgerSearch', 'ledgerTypes', 'ledgerSort', 'ledgerDir');
    }
}
