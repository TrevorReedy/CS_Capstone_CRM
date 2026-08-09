<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

if (!Permissions::can('reservations.update_status')) {
    layout_deny();
    exit;
}

$reservationId = (int)($_GET['id'] ?? 0);
if ($reservationId === 0) {
    header('Location: /modules/rfq/pipeline.php');
    exit;
}

$controller = new RFQController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleEditReservationPost($reservationId); // redirects + exits on success
}

layout_open();
$controller->editReservation($reservationId);
layout_close();
