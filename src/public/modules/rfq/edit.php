<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';
if (!Permissions::can('rfqs.edit')) {
    layout_deny();
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: /modules/rfq/pipeline.php');
    exit;
}

$controller = new RFQController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($_POST['_action'] ?? '') {
        'delete_reservation' => $controller->handleDeleteReservationPost((int)($_POST['reservation_id'] ?? 0)),
        default              => $controller->handleUpdatePost($id),
    };
}

layout_open();

$controller->edit($id);

layout_close();
