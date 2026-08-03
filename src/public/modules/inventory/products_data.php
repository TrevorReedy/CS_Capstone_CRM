<?php
/**
 * Server-side DataTables source for the Inventory products list.
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Modules\Inventory\InventoryRepository;

Auth::requireLogin();
header('Content-Type: application/json');

if (!Permissions::can('inventory.view')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $statusBadge = [
        'In Stock'  => 'rfq-badge-success',
        'Low Stock' => 'rfq-badge-low-stock',
        'No Stock'  => 'rfq-badge-out-of-stock',
    ];
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $response = InventoryRepository::listTable()->handle($_GET, static function (array $r) use ($statusBadge, $h) {
        $available = (int)$r['available_quantity'];
        $threshold = (int)$r['low_stock_threshold'];
        if ($available === 0)          { $status = 'No Stock'; }
        elseif ($available < $threshold) { $status = 'Low Stock'; }
        else                           { $status = 'In Stock'; }

        $id      = (int)$r['id'];
        $btn     = ' class="btn" style="font-size:0.85rem; padding:4px 10px;"';
        $delBtn  = ' class="btn" style="font-size:0.85rem; padding:4px 10px; background:#fde8e8; color:#b91c1c; border-color:#fca5a5;"';
        $actions = '<a href="/modules/inventory/products.php?page=detail&id=' . $id . '"' . $btn . '>Edit</a> '
                 . '<a href="/modules/inventory/products.php?page=stock&id=' . $id . '"' . $btn . '>Stock</a> '
                 . '<a href="/modules/inventory/products.php?page=delete&id=' . $id . '"' . $delBtn . '>Delete</a>';

        return [
            'sku'          => $h($r['sku']),
            'product_name' => '<a href="/modules/inventory/products.php?page=detail&id=' . $id . '" class="rfq-list-link">' . $h($r['product_name']) . '</a>',
            'price'        => '$' . number_format((float)$r['price'], 2),
            'available'    => $available,
            'reserved'     => (int)$r['reserved_quantity'],
            'status'       => '<span class="rfq-badge ' . $statusBadge[$status] . '">' . $status . '</span>',
            'actions'      => $actions,
        ];
    });

    echo json_encode($response);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'products_data.php');
}
