<?php
/**
 * Server-side DataTables source for the Campaigns list (top table on the
 * Campaigns page). The analytics/momentum sections use their own endpoints.
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignRepository;

Auth::requireLogin();
header('Content-Type: application/json');

if (!Permissions::can('campaigns.view')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $statusBadge = [
        'Draft'     => 'badge-neutral',
        'Scheduled' => 'badge-info',
        'Sent'      => 'badge-quoted',
        'Completed' => 'badge-success',
    ];
    $typeBadge = [
        'Email'          => 'badge-info',
        'SMS Simulation' => 'badge-warning',
    ];
    $h    = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $response = CampaignRepository::listTable()->handle($_GET, static function (array $c) use ($statusBadge, $typeBadge, $h) {
        return [
            'id'            => '#' . (int)$c['id'],
            'campaign_name' => '<a href="/modules/campaign/detail.php?id=' . (int)$c['id'] . '">' . $h($c['campaign_name']) . '</a>',
            'campaign_type' => '<span class="badge ' . ($typeBadge[$c['campaign_type']] ?? 'badge-neutral') . '">' . $h($c['campaign_type']) . '</span>',
            'status'        => '<span class="badge ' . ($statusBadge[$c['status']] ?? 'badge-neutral') . '">' . $h($c['status']) . '</span>',
            'sent_count'    => (int)$c['sent_count'],
            'created_at'    => date('M j, Y', strtotime($c['created_at'])),
        ];
    });

    echo json_encode($response);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'campaigns_data.php');
}
