<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Login throttling only. Auth::attempt() needs a database and lives in the
 * integration suite.
 *
 * The throttle is session-scoped by design (documented in KNOWN_LIMITATIONS as
 * a limitation, not an oversight): it stops scripted guessing from one browser
 * and cannot be used to lock a real user out from someone else's.
 */
#[CoversClass(Auth::class)]
final class AuthThrottleTest extends TestCase
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECS = 60;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    private function failTimes(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            Auth::recordFailure($email);
        }
    }

    /**
     * Reach into the throttle record without duplicating its key derivation —
     * the test should not have to know the record is keyed by md5 of the email.
     */
    private function ageTheLockout(int $seconds): void
    {
        foreach ($_SESSION as $key => $record) {
            if (str_starts_with((string) $key, 'login_failures_')) {
                $record['last'] = time() - $seconds;
                $_SESSION[$key] = $record;
            }
        }
    }

    #[Test]
    public function a_fresh_email_is_not_throttled(): void
    {
        $this->assertFalse(Auth::isThrottled('nobody@typhoncath.test'));
    }

    #[Test]
    public function stays_unthrottled_below_the_attempt_limit(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS - 1);

        $this->assertFalse(Auth::isThrottled('user@typhoncath.test'));
    }

    #[Test]
    public function throttles_on_reaching_the_attempt_limit(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS);

        $this->assertTrue(Auth::isThrottled('user@typhoncath.test'));
    }

    #[Test]
    public function the_lockout_lifts_once_the_window_has_passed(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS);
        $this->assertTrue(Auth::isThrottled('user@typhoncath.test'));

        $this->ageTheLockout(self::LOCKOUT_SECS + 1);

        $this->assertFalse(Auth::isThrottled('user@typhoncath.test'));
    }

    #[Test]
    public function the_lockout_holds_right_up_to_the_window_boundary(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS);
        $this->ageTheLockout(self::LOCKOUT_SECS - 1);

        $this->assertTrue(Auth::isThrottled('user@typhoncath.test'));
    }

    /**
     * Once the window lifts, the counter must actually reset — otherwise the
     * next single failure would re-trip the lockout immediately.
     */
    #[Test]
    public function the_failure_count_resets_when_the_window_lifts(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS);
        $this->ageTheLockout(self::LOCKOUT_SECS + 1);
        Auth::isThrottled('user@typhoncath.test');

        Auth::recordFailure('user@typhoncath.test');

        $this->assertFalse(Auth::isThrottled('user@typhoncath.test'));
    }

    #[Test]
    public function a_successful_login_clears_the_failures(): void
    {
        $this->failTimes('user@typhoncath.test', self::MAX_ATTEMPTS);

        Auth::clearFailures('user@typhoncath.test');

        $this->assertFalse(Auth::isThrottled('user@typhoncath.test'));
    }

    #[Test]
    public function one_locked_out_email_does_not_lock_out_another(): void
    {
        $this->failTimes('victim@typhoncath.test', self::MAX_ATTEMPTS);

        $this->assertTrue(Auth::isThrottled('victim@typhoncath.test'));
        $this->assertFalse(Auth::isThrottled('bystander@typhoncath.test'));
    }

    /**
     * Emails are normalised before keying, so an attacker cannot get five extra
     * guesses by changing the case or padding the address.
     */
    #[Test]
    public function the_throttle_key_is_case_and_whitespace_insensitive(): void
    {
        $this->failTimes('User@Typhoncath.test', self::MAX_ATTEMPTS);

        $this->assertTrue(Auth::isThrottled('user@typhoncath.test'));
        $this->assertTrue(Auth::isThrottled('  USER@TYPHONCATH.TEST  '));
    }
}
