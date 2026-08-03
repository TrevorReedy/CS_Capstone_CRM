<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

// Gate before dispatch. The check used to live in CampaignController::edit(),
// which only runs on the GET path — so handleUpdatePost() below wrote the
// campaign before anything verified the caller could edit it.
if (!Permissions::can('campaigns.edit')) {
    layout_deny();
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: /modules/campaign/campaigns.php');
    exit;
}

$controller = new CampaignController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleUpdatePost($id); // redirects + exits on success
}

layout_open();
$controller->edit($id);
layout_close();
