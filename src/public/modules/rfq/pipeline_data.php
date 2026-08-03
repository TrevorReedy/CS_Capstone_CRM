<?php
/**
 * Server-side DataTables source for the RFQ list. Returns one page of rows as
 * JSON ({draw, recordsTotal, recordsFiltered, data}). Sort/filter/paginate are
 * all done in SQL via App\Core\DataTable\ServerTable — see RFQRepository::listTable().
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Modules\RFQ\RFQRepository;

Auth::requireLogin();
header('Content-Type: application/json');

if (!Permissions::can('rfqs.view')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $stageBadge = [
        'New'         => 'rfq-badge-neutral',
        'In Review'   => 'rfq-badge-info',
        'Quoted'      => 'rfq-badge-quoted',
        'Negotiation' => 'rfq-badge-warning',
        'Won'         => 'rfq-badge-success',
        'Lost'        => 'rfq-badge-danger',
    ];
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $response = RFQRepository::listTable()->handle($_GET, static function (array $r) use ($stageBadge, $h) {
        return [
            'id'           => '#' . (int)$r['id'],
            'title'        => '<a href="/modules/rfq/detail.php?id=' . (int)$r['id'] . '" class="rfq-list-link">' . $h($r['title']) . '</a>',
            'account_name' => $r['account_name'] !== null && $r['account_name'] !== '' ? $h($r['account_name']) : '—',
            'stage'        => '<span class="rfq-badge ' . ($stageBadge[$r['stage']] ?? '') . '">' . $h($r['stage']) . '</span>',
            'created_at'   => date('M j, Y', strtotime($r['created_at'])),
            'updated_at'   => date('M j, Y', strtotime($r['updated_at'])),
        ];
    });

    echo json_encode($response);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'pipeline_data.php');
}
