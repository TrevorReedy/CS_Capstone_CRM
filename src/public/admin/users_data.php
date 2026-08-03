<?php
/**
 * Server-side DataTables source for the Admin users list.
 */
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\ErrorResponse;
use App\Core\Permissions;
use App\Core\Csrf;
use App\Modules\Admin\UserRepository;

Auth::requireLogin();
header('Content-Type: application/json');

if (!Permissions::can('admin.manage_users')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'message' => 'Forbidden']);
    exit;
}

try {
    $roleBadgeMap = [
        'Super Admin'       => 'rfq-badge-danger',
        'Admin'             => 'rfq-badge-warning',
        'Sales User'        => 'rfq-badge-info',
        'Marketing User'    => 'rfq-badge-success',
        'Inventory Manager' => 'rfq-badge-quoted',
    ];
    $h         = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $currentId = (int)(Auth::user()['id'] ?? 0);
    $csrf      = Csrf::field();

    $response = UserRepository::listTable()->handle($_GET, static function (array $r) use ($roleBadgeMap, $h, $currentId, $csrf) {
        $id       = (int)$r['id'];
        $isCustom = !empty($r['owner_user_id']);
        $badge    = $isCustom ? 'rfq-badge-warning' : ($roleBadgeMap[$r['role_name']] ?? 'rfq-badge-neutral');
        $isSelf   = $id === $currentId;

        $name = $h($r['name']);
        if ($isSelf) {
            $name .= ' <span class="rfq-badge rfq-badge-neutral" style="font-size:0.65rem;margin-left:4px;">you</span>';
        }

        $actions = '<a href="/admin/users.php?action=edit&id=' . $id . '" class="btn btn-secondary btn-sm">Edit</a>';
        if (!$isSelf) {
            $actions .= ' <form method="POST" action="/admin/users.php" style="display:inline;"'
                . ' onsubmit="return confirm(\'Delete ' . $h(addslashes($r['name'])) . '? This cannot be undone.\');">'
                . $csrf
                . '<input type="hidden" name="_action" value="delete">'
                . '<input type="hidden" name="user_id" value="' . $id . '">'
                . '<button type="submit" class="btn btn-danger btn-sm">&times;</button>'
                . '</form>';
        }

        return [
            'name'       => $name,
            'email'      => $h($r['email']),
            'role'       => '<span class="rfq-badge ' . $badge . '">' . ($isCustom ? 'Custom' : $h($r['role_name'])) . '</span>',
            'created_at' => date('M j, Y', strtotime($r['created_at'])),
            'actions'    => $actions,
        ];
    });

    echo json_encode($response);
} catch (Throwable $e) {
    // Never echo the PDO message: it leaks schema and SQL to the client.
    ErrorResponse::json($e, 'users_data.php');
}
