<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Deliberately does NOT include app/Core/bootstrap.php. That file calls
 * session_start() and header(), neither of which works under the CLI SAPI —
 * session_start() emits a warning with no active request, and header() is a
 * no-op that would mask real behaviour. Everything the tests need from it is
 * reproduced here instead:
 *
 *   - class autoloading, via Composer (composer.json maps App\ -> app/)
 *   - $_SESSION as a plain array. Auth, Csrf and Permissions only ever read and
 *     write $_SESSION keys; they never require a real session handler, so an
 *     array superglobal is a faithful stand-in and lets each test reset state
 *     with a single assignment.
 *   - the Shared/layout.php helper functions, which are plain functions the
 *     autoloader cannot reach.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Some views and controllers call the layout helpers. Loading them here keeps a
// test that touches a view from fataling on an undefined function.
require_once __DIR__ . '/../app/Shared/layout.php';

// Constant defined by app/Core/bootstrap.php; a few paths reference it.
if (!defined('APP_PATH')) {
    define('APP_PATH', __DIR__ . '/../app/');
}

$_SESSION = [];
$_POST    = [];
$_GET     = [];

// Keep test output clean: the app logs to storage/logs in normal operation, but
// error_log() calls from code under test (Auth::recordFailure, the permission
// refresh catch block) would otherwise print into the PHPUnit report.
ini_set('error_log', __DIR__ . '/../storage/logs/test.log');
