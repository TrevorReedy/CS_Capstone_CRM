<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Permissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Permissions::can() calls refreshIfStale(), which queries the database when
 * the session's cached copy is older than REFRESH_AFTER_SECONDS (60). Every
 * test here sets a fresh permissions_synced_at so the cache is considered
 * current and no connection is attempted — the refresh path itself belongs to
 * the integration suite, where there is a database to refresh from.
 *
 * Permissions::require() is not tested here; it calls exit on denial. Its
 * decision is can(), which is.
 */
#[CoversClass(Permissions::class)]
final class PermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /** @param string[] $permissions */
    private function signIn(string $role, array $permissions): void
    {
        $_SESSION['user'] = [
            'id'          => 7,
            'name'        => 'Test User',
            'email'       => 'test@typhoncath.test',
            'role'        => $role,
            'permissions' => $permissions,
        ];
        $_SESSION['permissions_synced_at'] = time();
    }

    #[Test]
    public function denies_everything_when_nobody_is_signed_in(): void
    {
        $this->assertFalse(Permissions::can('customers.view'));
        $this->assertFalse(Permissions::can('admin.manage_users'));
    }

    #[Test]
    public function grants_a_permission_the_role_holds(): void
    {
        $this->signIn('Sales User', ['customers.view', 'rfqs.create']);

        $this->assertTrue(Permissions::can('customers.view'));
        $this->assertTrue(Permissions::can('rfqs.create'));
    }

    #[Test]
    public function denies_a_permission_the_role_does_not_hold(): void
    {
        $this->signIn('Sales User', ['customers.view']);

        $this->assertFalse(Permissions::can('admin.manage_users'));
    }

    /**
     * Super Admin is the documented break-glass override: savePermissions()
     * never writes matrix rows for it, so without this bypass an admin could
     * lock everyone out of the permission screen itself.
     */
    #[Test]
    public function super_admin_bypasses_the_matrix_entirely(): void
    {
        $this->signIn('Super Admin', []);

        $this->assertTrue(Permissions::can('admin.manage_permissions'));
        $this->assertTrue(Permissions::can('anything.at.all'));
    }

    /**
     * The counterpart, and the one that makes the matrix mean anything: Admin
     * is a normal role governed by role_permissions. Its full grant comes from
     * seed.sql, not from a hard-coded exception.
     */
    #[Test]
    public function admin_is_governed_by_the_matrix_like_any_other_role(): void
    {
        $this->signIn('Admin', ['customers.view']);

        $this->assertTrue(Permissions::can('customers.view'));
        $this->assertFalse(Permissions::can('admin.manage_permissions'));
    }

    #[Test]
    public function permission_matching_is_exact_not_prefix_based(): void
    {
        $this->signIn('Sales User', ['customers.view']);

        $this->assertFalse(Permissions::can('customers'));
        $this->assertFalse(Permissions::can('customers.view.all'));
        $this->assertFalse(Permissions::can('Customers.View'));
    }

    #[Test]
    public function a_user_with_no_permissions_key_is_denied_rather_than_erroring(): void
    {
        $_SESSION['user'] = ['id' => 7, 'role' => 'Sales User'];
        $_SESSION['permissions_synced_at'] = time();

        $this->assertFalse(Permissions::can('customers.view'));
    }

    /**
     * The role name is compared with ===, so a permission list containing the
     * string 'Super Admin' must not be mistaken for the role.
     */
    #[Test]
    public function the_super_admin_bypass_keys_on_the_role_not_the_permission_list(): void
    {
        $this->signIn('Sales User', ['Super Admin']);

        $this->assertFalse(Permissions::can('admin.manage_permissions'));
    }
}
