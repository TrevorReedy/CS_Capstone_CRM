<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignRepository;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

header('Content-Type: application/json');

// JSON endpoint — deny in JSON, not with the HTML 403 page.
if (!Permissions::can('campaigns.edit')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$tagFilter  = trim($_POST['tag_filter']  ?? '');
$accountIds = array_filter(array_map('intval', (array)($_POST['account_ids'] ?? [])));
$contactIds = array_filter(array_map('intval', (array)($_POST['contact_ids'] ?? [])));

$repo   = new CampaignRepository();
$counts = $repo->previewAudienceCount($tagFilter, array_values($accountIds), array_values($contactIds));

echo json_encode($counts);
