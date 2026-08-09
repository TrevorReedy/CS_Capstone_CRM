<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignRepository;

Auth::requireLogin();

header('Content-Type: application/json');

if (!Permissions::can('campaigns.metrics')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $validSegments = ['all', 'accounts', 'contacts'];
    $validRanges   = ['7d', '30d', '12w', '180d', 'custom'];

    $range = $_GET['range'] ?? '12w';
    if (!in_array($range, $validRanges, true)) {
        $range = '12w';
    }

    $segment = $_GET['segment'] ?? 'all';
    if (!in_array($segment, $validSegments, true)) {
        $segment = 'all';
    }

    $to      = date('Y-m-d 23:59:59');
    $groupBy = 'week';

    switch ($range) {
        case '7d':
            $from = date('Y-m-d', strtotime('-7 days'));
            $groupBy = 'day';
            break;

        case '30d':
            $from = date('Y-m-d', strtotime('-30 days'));
            $groupBy = 'day';
            break;

        case '180d':
            $from = date('Y-m-d', strtotime('-180 days'));
            break;

        case 'custom':
            $rawFrom = $_GET['from'] ?? '';
            $rawTo   = $_GET['to'] ?? '';

            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)
                ? $rawFrom
                : date('Y-m-d', strtotime('-12 weeks'));

            $toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)
                ? $rawTo
                : date('Y-m-d');

            $to = $toDate . ' 23:59:59';

            $span = (strtotime($to) - strtotime($from)) / 86400;
            $groupBy = $span <= 60 ? 'day' : 'week';
            break;

        case '12w':
        default:
            $from = date('Y-m-d', strtotime('-12 weeks'));
            break;
    }

    $repo = new CampaignRepository();
    $rows = $repo->campaignMomentum($from, $to, $groupBy, $segment);

echo json_encode([
    'labels'        => array_values(array_column($rows, 'period_label')),
    'recipients'    => array_values(array_map('intval', array_column($rows, 'total_recipients'))),
    'sent'          => array_values(array_map('intval', array_column($rows, 'campaigns_sent'))),
]);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'momentum_data.php');
}