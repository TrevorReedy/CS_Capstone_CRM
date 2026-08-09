<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Modules\Campaign\CampaignController;
use App\Core\Permissions;

Auth::requireLogin();
if (!Permissions::can('campaigns.view')) {
    layout_deny();
    exit;
}


layout_open();
$controller = new CampaignController();
$controller->index();
layout_close();
