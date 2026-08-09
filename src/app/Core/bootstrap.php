<?php
declare(strict_types=1);

//starts php session
// A PHP session is a mechanism that stores user data on the 
// web server so it can be accessed across multiple pages of a 
// website 

// the important part of a session is allowing login state to work
// For example, after a user logs in, you can store:

// $_SESSION['user'] = [
//     'id' => 1,
//     'name' => 'Demo Admin',
//     'role' => 'Admin'
// ];

define('APP_PATH', __DIR__ . '/../');

// ─────────────────────────────────────────────────────────────────────────────
// Error logging
//
// PHP's default error_log writes next to whichever script raised the error,
// which put stack traces (and absolute hosting paths) inside the public
// document root. Pin every message to one file outside the web root instead.
// ─────────────────────────────────────────────────────────────────────────────
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
// Only redirect if we can actually write there. Pointing error_log at an
// unwritable path makes PHP drop the message entirely — worse than the default.
// Falling through leaves errors going to the SAPI log (Apache's error log /
// container stderr), which is outside the document root either way.
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/application.log');
}
// Never render an exception into the response body — see the JSON handlers,
// which return a request id instead.
ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? '1' : '0');

// ─────────────────────────────────────────────────────────────────────────────
// Security response headers
//
// Set here rather than only in public/.htaccess because .htaccess is ignored
// unless Apache is configured with AllowOverride, and the production target is
// shared cPanel hosting whose httpd.conf we do not control. Emitting them from
// PHP means they apply wherever the app runs.
//
// The CSP allows 'unsafe-inline' for script and style: the views and DataTables
// initialisers are inline today, and a nonce-based policy is a separate change.
// It still blocks external script origins, framing, and form hijacking.
// ─────────────────────────────────────────────────────────────────────────────
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Session
//
// Cookie parameters and strict mode only take effect if they are set *before*
// session_start(). This block used to be a bare session_start(), so the session
// cookie carried none of these flags: readable from JavaScript, sent over plain
// HTTP, and attached to cross-site requests.
// ─────────────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Reject a session id the server never issued (session fixation).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(getenv('SESSION_NAME') ?: 'typhon_cath_crm_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        // Setting Secure on plain HTTP would make the cookie undeliverable and
        // lock local development out entirely, so follow the actual scheme
        // unless SESSION_SECURE forces it on.
        'secure'   => $isHttps || getenv('SESSION_SECURE') === 'true',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Idle and absolute expiry. Without these a stolen session id stayed valid
    // until the browser closed.
    $now      = time();
    $idleMax  = (int)(getenv('SESSION_IDLE_TIMEOUT')     ?: 1800);    // 30 min
    $totalMax = (int)(getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 28800);   // 8 h

    $idleExpired  = isset($_SESSION['last_activity']) && $now - $_SESSION['last_activity'] > $idleMax;
    $totalExpired = isset($_SESSION['created_at'])    && $now - $_SESSION['created_at']    > $totalMax;

    if ($idleExpired || $totalExpired) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['expired'] = true;
    }

    $_SESSION['created_at'] ??= $now;
    $_SESSION['last_activity'] = $now;
}

// This part:

// spl_autoload_register(...)

// means you do not have to manually require every class file.


// MANUAL REQUIRE EXAMPLE
//   require_once '../app/Core/Auth.php';

// You can just write:
//    use App\Core\Auth;
//    use App\Core\Database;

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Shared page-layout helpers (layout_open/layout_close/layout_deny/page_header).
// Plain functions, not a class, so the autoloader doesn't cover them — load here
// so every page and view can rely on them.
require_once __DIR__ . '/../Shared/layout.php';
