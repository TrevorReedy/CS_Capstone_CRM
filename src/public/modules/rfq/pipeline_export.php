<?php
/**
 * Export endpoint for the RFQ list (CSV / XML / PDF-via-print). Reuses the same
 * ServerTable source as pipeline_data.php with the current global search +
 * per-column filters + sort + page window, so the export contains exactly the
 * rows on screen.
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQRepository;
use App\Core\DataTable\Exporter;

Auth::requireLogin();

if (!Permissions::can('rfqs.view')) {
    http_response_code(403);
    exit('Forbidden');
}

$format = (string)($_GET['format'] ?? 'csv');
$rows   = RFQRepository::listTable()->exportRows($_GET);

$columns = [
    'id'           => '#',
    'title'        => 'Title',
    'account_name' => 'Account',
    'stage'        => 'Stage',
    'created_at'   => 'Created',
    'updated_at'   => 'Updated',
];

$data = array_map(static fn(array $r) => [
    'id'           => (int)$r['id'],
    'title'        => $r['title'],
    'account_name' => $r['account_name'] ?? '',
    'stage'        => $r['stage'],
    'created_at'   => date('Y-m-d', strtotime($r['created_at'])),
    'updated_at'   => date('Y-m-d', strtotime($r['updated_at'])),
], $rows);

Exporter::stream($format, $columns, $data, 'rfqs', 'RFQ List');
