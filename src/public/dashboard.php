<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Dashboard\DashboardController;

Auth::requireLogin();

if (!Permissions::can('dashboard.view')) {
    layout_deny();
    exit;
}

layout_open();

(new DashboardController())->index();

layout_close();
