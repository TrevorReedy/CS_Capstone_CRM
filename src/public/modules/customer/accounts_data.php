<?php
/**
 * Server-side DataTables source for the Customer accounts list.
 */
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Modules\Customer\CustomerRepository;

Auth::requireLogin();
header('Content-Type: application/json');

if (!Permissions::can('customers.view')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $response = CustomerRepository::listTable()->handle($_GET, static function (array $r) use ($h) {
        return [
            'account_name' => '<a href="/modules/customer/account_detail.php?id=' . (int)$r['id'] . '">' . $h($r['account_name']) . '</a>',
            'email'        => $h($r['email'] ?? ''),
            'phone'        => $h($r['phone'] ?? ''),
            'industry'     => $h($r['industry'] ?? ''),
            'source'       => $h($r['source'] ?? ''),
            'tags'         => $h($r['tags'] ?? ''),
        ];
    });

    echo json_encode($response);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'accounts_data.php');
}
