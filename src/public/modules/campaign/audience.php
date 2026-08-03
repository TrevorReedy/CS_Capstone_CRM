<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

if (!Permissions::can('campaigns.edit')) {
    layout_deny();
    exit;
}

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if ($campaignId === 0) {
    header('Location: /modules/campaign/campaigns.php');
    exit;
}

$controller = new CampaignController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    match ($action) {
        'remove_segment' => (function () use ($controller, $campaignId) {
            $segmentName = trim($_POST['segment_name'] ?? '');
            if ($segmentName !== '') $controller->handleRemoveSegment($segmentName, $campaignId);
        })(),
        'save_preset'    => $controller->handleSavePresetPost($campaignId),
        'apply_preset'   => $controller->handleApplyPresetPost($campaignId),
        default          => $controller->handleAudiencePost($campaignId),
    };
}

layout_open();
$controller->audience($campaignId);
layout_close();
