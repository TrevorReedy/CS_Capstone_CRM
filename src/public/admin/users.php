<?php
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Admin\AdminController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../app/Middleware/csrf.php';

if (!Permissions::can('admin.manage_users')) {
    layout_deny();
    exit;
}

$controller = new AdminController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    match ($action) {
        'create'             => $controller->handleCreateUserPost(),
        'edit'               => $controller->handleEditUserPost($userId),
        'delete'             => $controller->handleDeleteUserPost($userId),
        'create_custom_role' => $controller->handleCreateCustomRolePost(),
        'remove_custom_role' => $controller->handleRemoveCustomRolePost(),
        default              => null,
    };
}

layout_open();
$controller->dispatch();
layout_close();
