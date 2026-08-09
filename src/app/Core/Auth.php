<?php
namespace App\Core;

use PDO;

class Auth
{
    // Checks whether someone is logged in.
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }
    // returns current user
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
    // preforms a user check to verify credentails 
    // if user is not logged in it sends them to /login

    // not the auth function, just a basic check
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }
    }
    // ── Login throttling ──────────────────────────────────────────────────────
    // Session-scoped, so it costs no schema change and cannot be used to lock a
    // real user out of their own account from someone else's browser. It stops
    // the scripted-guessing case; a distributed attacker rotating cookies needs
    // an IP-keyed store (Redis/DB), which is the follow-up if this ever faces
    // the public internet.
    private const MAX_ATTEMPTS  = 5;
    private const LOCKOUT_SECS  = 60;

    /** Key failures per email so one address's lockout doesn't block another. */
    private static function throttleKey(string $email): string
    {
        return 'login_failures_' . md5(strtolower(trim($email)));
    }

    public static function isThrottled(string $email): bool
    {
        $record = $_SESSION[self::throttleKey($email)] ?? null;
        if (!$record || $record['count'] < self::MAX_ATTEMPTS) {
            return false;
        }

        // Window elapsed — forget the failures and let them try again.
        if (time() - $record['last'] > self::LOCKOUT_SECS) {
            self::clearFailures($email);
            return false;
        }

        return true;
    }

    public static function recordFailure(string $email): void
    {
        $key    = self::throttleKey($email);
        $record = $_SESSION[$key] ?? ['count' => 0, 'last' => 0];
        $_SESSION[$key] = ['count' => $record['count'] + 1, 'last' => time()];

        error_log(sprintf(
            'Failed login for %s from %s',
            strtolower(trim($email)),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ));
    }

    public static function clearFailures(string $email): void
    {
        unset($_SESSION[self::throttleKey($email)]);
    }

    public static function attempt(string $email, string $password): bool
    {
        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.password_hash, u.role_id, r.role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = ?
            LIMIT 1
        ");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Upgrade the stored hash if PHP's default cost/algorithm has moved on.
        // This is the only moment the plaintext is available to do it.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
        }

        $permStmt = $db->prepare("SELECT permission FROM role_permissions WHERE role_id = ?");
        $permStmt->execute([$user['role_id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

        // Regenerate session ID on login to prevent session fixation.
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'          => (int)$user['id'],
            'name'        => $user['name'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'permissions' => $permissions,
        ];
        // Just loaded — tells Permissions::refreshIfStale() not to re-query
        // immediately on the first page after login.
        $_SESSION['permissions_synced_at'] = time();

        return true;
    }
    // clears the session and logs them out
    public static function logout(): void
    {
        $_SESSION = [];

        // session_destroy() drops the server-side data but leaves the cookie in
        // the browser pointing at a dead id. Expire it explicitly.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
