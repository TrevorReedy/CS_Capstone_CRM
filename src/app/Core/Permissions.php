<?php
namespace App\Core;


// Example permissions could be:

// customers.view
// customers.edit
// rfqs.create
// rfqs.update_stage
// campaigns.create
// inventory.update_stock
// admin.manage_users



// EXAMPLE
// On an RFQ create page:

// use App\Core\Auth;
// use App\Core\Permissions;

// Auth::requireLogin();
// Permissions::require('rfqs.create');

// That means:

// User must be logged in
// AND
// User must have permission to create RFQs
class Permissions
{
    /**
     * Permissions are copied into the session at login. When an admin edits the
     * matrix, everyone already signed in keeps their old rights until they log
     * out — including rights that were just revoked. Re-reading the table on
     * every check would be correct but adds a query to every page load, so
     * instead the session copy is refreshed if it is older than this.
     */
    private const REFRESH_AFTER_SECONDS = 60;

    /**
     * Reload this user's permissions from the database if the cached copy has
     * gone stale. Cheap: one indexed lookup at most once a minute per session.
     */
    private static function refreshIfStale(): void
    {
        if (empty($_SESSION['user'])) {
            return;
        }

        $lastSync = $_SESSION['permissions_synced_at'] ?? 0;
        if (time() - $lastSync < self::REFRESH_AFTER_SECONDS) {
            return;
        }

        try {
            $stmt = Database::connection()->prepare(
                "SELECT rp.permission
                   FROM role_permissions rp
                   JOIN users u ON u.role_id = rp.role_id
                  WHERE u.id = ?"
            );
            $stmt->execute([$_SESSION['user']['id']]);
            $_SESSION['user']['permissions'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $_SESSION['permissions_synced_at'] = time();
        } catch (\Throwable $e) {
            // A database blip must not log everyone out or silently grant
            // access. Keep the cached copy and try again on the next check.
            error_log('Permission refresh failed: ' . $e->getMessage());
        }
    }

    public static function can(string $permission): bool
    {
        self::refreshIfStale();

        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Super Admin is a deliberate break-glass override: savePermissions()
        // never stores rows for role_id 1, so the matrix cannot lock the system
        // out of its own permission screen. Every other role — including Admin —
        // is governed by role_permissions, which is what makes the matrix mean
        // anything. Admin's full grant is seeded in seed.sql.
        if (($user['role'] ?? '') === 'Super Admin') {
            return true;
        }

        return in_array($permission, $user['permissions'] ?? [], true);
    }

    public static function require(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }
}
