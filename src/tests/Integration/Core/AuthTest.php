<?php
declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Auth::attempt() against the real users table and the real bcrypt hashes in
 * seed.sql. The point of testing this with a database is that the query joins
 * roles and role_permissions — a login that succeeds but loads no permissions
 * is indistinguishable from a broken one until someone hits a 403.
 */
#[CoversClass(Auth::class)]
final class AuthTest extends IntegrationTestCase
{
    /** The demo credential, public by design — it is printed in the README. */
    private const DEMO_EMAIL    = 'admin@typhoncath.test';
    private const DEMO_PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();

        // attempt() calls session_regenerate_id(), which needs a session to
        // regenerate. Under the CLI SAPI there are no headers to worry about.
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    #[Test]
    public function the_seeded_demo_credential_logs_in(): void
    {
        $this->assertTrue(Auth::attempt(self::DEMO_EMAIL, self::DEMO_PASSWORD));
        $this->assertTrue(Auth::check());
    }

    #[Test]
    public function a_successful_login_populates_the_session_the_app_expects(): void
    {
        Auth::attempt(self::DEMO_EMAIL, self::DEMO_PASSWORD);
        $user = Auth::user();

        $this->assertIsArray($user);
        $this->assertIsInt($user['id']);
        $this->assertSame(self::DEMO_EMAIL, $user['email']);
        $this->assertSame('Super Admin', $user['role']);
        $this->assertIsArray($user['permissions']);
        $this->assertArrayNotHasKey('password_hash', $user, 'the hash must never reach the session');
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $this->assertFalse(Auth::attempt(self::DEMO_EMAIL, 'not-the-password'));
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function an_unknown_email_is_rejected(): void
    {
        $this->assertFalse(Auth::attempt('nobody@typhoncath.test', self::DEMO_PASSWORD));
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function an_empty_password_is_rejected(): void
    {
        $this->assertFalse(Auth::attempt(self::DEMO_EMAIL, ''));
    }

    /**
     * The lookup lowercases and trims, so the same account is reachable however
     * the user typed it — while still going through password_verify.
     */
    #[Test]
    public function the_email_lookup_is_case_and_whitespace_insensitive(): void
    {
        $this->assertTrue(Auth::attempt('  ADMIN@TYPHONCATH.TEST  ', self::DEMO_PASSWORD));
    }

    #[Test]
    public function logging_out_clears_the_session(): void
    {
        Auth::attempt(self::DEMO_EMAIL, self::DEMO_PASSWORD);
        $this->assertTrue(Auth::check());

        Auth::logout();

        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
    }

    /**
     * Login is the only moment the plaintext exists, so it is the only moment a
     * weak stored hash can be upgraded. A user whose hash predates a cost bump
     * should be transparently re-hashed.
     */
    #[Test]
    public function a_stale_hash_is_upgraded_on_successful_login(): void
    {
        $weakHash = password_hash('rehash-me-please', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, role_id)
             VALUES (?, ?, ?, (SELECT id FROM roles WHERE role_name = ? LIMIT 1))'
        )->execute(['Stale Hash', 'stale@typhoncath.test', $weakHash, 'Sales User']);

        $this->assertTrue(Auth::attempt('stale@typhoncath.test', 'rehash-me-please'));

        $stored = $this->fetchOne('SELECT password_hash FROM users WHERE email = ?', ['stale@typhoncath.test']);
        $this->assertNotSame($weakHash, $stored['password_hash'], 'the weak hash should have been replaced');
        $this->assertTrue(password_verify('rehash-me-please', $stored['password_hash']));
        $this->assertFalse(password_needs_rehash($stored['password_hash'], PASSWORD_BCRYPT));
    }

    #[Test]
    public function an_up_to_date_hash_is_left_alone(): void
    {
        $before = $this->fetchOne('SELECT password_hash FROM users WHERE email = ?', [self::DEMO_EMAIL]);

        Auth::attempt(self::DEMO_EMAIL, self::DEMO_PASSWORD);

        $after = $this->fetchOne('SELECT password_hash FROM users WHERE email = ?', [self::DEMO_EMAIL]);
        $this->assertSame($before['password_hash'], $after['password_hash']);
    }

    /**
     * A role's grants are copied into the session at login. If this join breaks,
     * every non-Super-Admin silently loses access to everything.
     */
    #[Test]
    public function a_non_super_admin_receives_its_roles_permissions(): void
    {
        $hash = password_hash('sales-password', PASSWORD_BCRYPT);
        $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, role_id)
             VALUES (?, ?, ?, (SELECT id FROM roles WHERE role_name = ? LIMIT 1))'
        )->execute(['Sales Tester', 'sales-test@typhoncath.test', $hash, 'Sales User']);

        $expected = $this->db->query(
            "SELECT rp.permission FROM role_permissions rp
               JOIN roles r ON r.id = rp.role_id
              WHERE r.role_name = 'Sales User'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertTrue(Auth::attempt('sales-test@typhoncath.test', 'sales-password'));

        $this->assertNotEmpty($expected, 'seed.sql should grant the Sales User role something');
        $this->assertEqualsCanonicalizing($expected, Auth::user()['permissions']);
    }
}
