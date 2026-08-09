<?php
/**
 * Export endpoint for the Admin users list (CSV / XML / PDF-via-print).
 */
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Admin\UserRepository;
use App\Core\DataTable\Exporter;

Auth::requireLogin();

if (!Permissions::can('admin.manage_users')) {
    http_response_code(403);
    exit('Forbidden');
}

$format = (string)($_GET['format'] ?? 'csv');
$rows   = UserRepository::listTable()->exportRows($_GET);

$columns = [
    'name'       => 'Name',
    'email'      => 'Email',
    'role'       => 'Role',
    'created_at' => 'Created',
];

$data = array_map(static fn(array $r) => [
    'name'       => $r['name'],
    'email'      => $r['email'],
    'role'       => !empty($r['owner_user_id']) ? 'Custom' : $r['role_name'],
    'created_at' => date('Y-m-d', strtotime($r['created_at'])),
], $rows);

Exporter::stream($format, $columns, $data, 'users', 'Users');
