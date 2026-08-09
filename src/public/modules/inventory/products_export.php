<?php
/**
 * Export endpoint for the Inventory products list (CSV / XML / PDF-via-print).
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Inventory\InventoryRepository;
use App\Core\DataTable\Exporter;

Auth::requireLogin();

if (!Permissions::can('inventory.view')) {
    http_response_code(403);
    exit('Forbidden');
}

$format = (string)($_GET['format'] ?? 'csv');
$rows   = InventoryRepository::listTable()->exportRows($_GET);

$columns = [
    'sku'          => 'SKU',
    'product_name' => 'Product Name',
    'price'        => 'Price',
    'available'    => 'Available',
    'reserved'     => 'Reserved',
    'status'       => 'Status',
];

$data = array_map(static function (array $r) {
    $available = (int)$r['available_quantity'];
    $threshold = (int)$r['low_stock_threshold'];
    if ($available === 0)            { $status = 'No Stock'; }
    elseif ($available < $threshold) { $status = 'Low Stock'; }
    else                             { $status = 'In Stock'; }

    return [
        'sku'          => $r['sku'],
        'product_name' => $r['product_name'],
        'price'        => number_format((float)$r['price'], 2),
        'available'    => $available,
        'reserved'     => (int)$r['reserved_quantity'],
        'status'       => $status,
    ];
}, $rows);

Exporter::stream($format, $columns, $data, 'products', 'Inventory Products');
