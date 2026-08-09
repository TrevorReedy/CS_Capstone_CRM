<?php
use App\Core\Auth;

$router->get('/admin/users', function (array $p) {
    Auth::requireLogin();
    include APP_PATH . 'Shared/header.php';
    include APP_PATH . 'Shared/sidebar.php';
    include APP_PATH . 'Modules/Admin/views/users.php';
    include APP_PATH . 'Shared/footer.php';
});
