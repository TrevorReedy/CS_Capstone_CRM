<?php
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Admin\AdminController;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../app/Middleware/csrf.php';

// Gate before the controller runs: handleSaveMatrixPost() below rewrites the
// whole permission matrix, so a login-only check here is a privilege-escalation
// path for every non-admin role.
if (!Permissions::can('admin.manage_permissions')) {
    layout_deny();
    exit;
}

$controller = new AdminController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handleSaveMatrixPost(); // redirects + exits on success
}

layout_open();
$controller->permissionMatrix();
layout_close();
