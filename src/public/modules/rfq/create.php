<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Modules\RFQ\RFQController;
use App\Core\Permissions;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';
if (!Permissions::can('rfqs.create')) {
    layout_deny();
    exit;
}

$controller = new RFQController();

// Process POST before any output so the redirect header can be sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleCreatePost(); // redirects + exits on success
}

layout_open();

$controller->create();

layout_close();
