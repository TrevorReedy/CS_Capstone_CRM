<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';


use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQController;

Auth::requireLogin();
if (!Permissions::can('rfqs.view')) {
    layout_deny();
    exit;
}

layout_open();

$controller = new RFQController();
$controller->winRate();

layout_close();
