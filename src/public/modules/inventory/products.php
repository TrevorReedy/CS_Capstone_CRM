<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Inventory\InventoryController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

// Shows the shared styled 403 page (same one the RFQ module uses) and halts
// the request when the current user lacks the given permission.
function denyUnlessAllowed(string $permission): void
{
    if (!Permissions::can($permission)) {
        layout_deny();
        exit;
    }
}

$controller = new InventoryController();
$page       = $_GET['page'] ?? 'list';
$isPost     = $_SERVER['REQUEST_METHOD'] === 'POST';

// ── Route ──────────────────────────────────────────────────────────────────
if ($page === 'detail') {
    if ($isPost) {
        denyUnlessAllowed(!empty($_POST['id']) ? 'inventory.edit' : 'inventory.create');
        $controller->save();
        exit;
    }
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->show();

} elseif ($page === 'stock') {
    if ($isPost) {
        denyUnlessAllowed('inventory.update_stock');
        $controller->updateStock();
        exit;
    }
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->editStock();

} elseif ($page === 'reservations') {
    if ($isPost) {
        denyUnlessAllowed('inventory.reserve');
        $controller->updateReservation();
        exit;
    }
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->reservations();

} elseif ($page === 'ledger') {
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->ledger();

} elseif ($page === 'ledger_print') {
    // Bare printable page: renders its own full HTML document, so none of
    // the shared header/sidebar/footer chrome is included here.
    denyUnlessAllowed('inventory.view');
    $controller->ledgerPrint();
    exit;

} elseif ($page === 'delete') {
    denyUnlessAllowed('inventory.edit');
    if ($isPost) {
        $controller->handleDelete();
        exit;
    }
    // GET with ?page=delete&id=X shows a confirmation page
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->confirmDelete();

} else {
    // Default: product list
    denyUnlessAllowed('inventory.view');
    layout_open();
    $controller->index();
}

layout_close();
