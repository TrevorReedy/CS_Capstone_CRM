<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Auth;

// POST only. As a GET endpoint this was a one-click — or one <img src="…"> —
// logout that any page on the internet could trigger, with no CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header('Location: /dashboard.php');
    exit;
}

// Rejects the request unless it carries this session's CSRF token.
require_once __DIR__ . '/../app/Middleware/csrf.php';

Auth::logout();

header('Location: /login.php');
exit;
