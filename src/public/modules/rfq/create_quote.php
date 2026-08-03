<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

if (!Permissions::can('quotes.create')) {
    layout_deny();
    exit;
}

$rfqId = (int)($_GET['rfq_id'] ?? $_POST['rfq_id'] ?? 0);

if ($rfqId === 0) {
    header('Location: /modules/rfq/pipeline.php');
    exit;
}

$controller = new RFQController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleCreateQuotePost(); // redirects + exits on success
}

layout_open();

$controller->createQuote($rfqId);

layout_close();
