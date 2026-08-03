<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

if (!Permissions::can('rfqs.view')) {
    layout_deny();
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: /modules/rfq/pipeline.php');
    exit;
}

// Each _action below mutates different state, so gate them individually here
// rather than relying on the two checks that happen to live in the controller.
// Deleting a reservation releases stock, so it shares reservations.update_status.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionPermissions = [
        'stage'                     => 'rfqs.update_stage',
        'delete'                    => 'rfqs.delete',
        'delete_quote'              => 'quotes.delete',
        'update_reservation_status' => 'reservations.update_status',
        'delete_reservation'        => 'reservations.update_status',
    ];

    $action = (string)($_POST['_action'] ?? '');
    if (!isset($actionPermissions[$action]) || !Permissions::can($actionPermissions[$action])) {
        layout_deny();
        exit;
    }
}

$controller = new RFQController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($_POST['_action'] ?? '') {
        'stage'                     => $controller->handleUpdateStagePost($id),
        'delete'                    => $controller->handleDeletePost($id),
        'delete_quote'              => $controller->handleDeleteQuotePost((int)($_POST['quote_id'] ?? 0)),
        'update_reservation_status' => $controller->handleUpdateReservationStatusPost((int)($_POST['reservation_id'] ?? 0)),
        'delete_reservation'        => $controller->handleDeleteReservationPost((int)($_POST['reservation_id'] ?? 0)),
        default                     => null,
    };
}

layout_open();

$controller->show($id);

layout_close();
