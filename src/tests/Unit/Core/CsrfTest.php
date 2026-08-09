<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Csrf::check() is not exercised here: it calls exit on failure, which would
 * take PHPUnit down with it. check() is a thin wrapper — method allow-list plus
 * validate() — so testing validate() directly covers the decision it makes.
 */
#[CoversClass(Csrf::class)]
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST    = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    #[Test]
    public function token_is_generated_once_and_reused_for_the_session(): void
    {
        $first  = Csrf::token();
        $second = Csrf::token();

        $this->assertSame($first, $second, 'a second call must not rotate the token');
        $this->assertSame(64, strlen($first), '32 random bytes hex-encoded');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    #[Test]
    public function separate_sessions_get_different_tokens(): void
    {
        $a = Csrf::token();

        $_SESSION = [];
        $b = Csrf::token();

        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function field_renders_a_hidden_input_carrying_the_session_token(): void
    {
        $html = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString(Csrf::token(), $html);
    }

    #[Test]
    public function meta_tag_carries_the_same_token_as_the_form_field(): void
    {
        $this->assertStringContainsString(Csrf::token(), Csrf::metaTag());
        $this->assertStringContainsString('name="csrf-token"', Csrf::metaTag());
    }

    /**
     * The token is hex, so it can never itself contain a quote — but the
     * escaping is what stops a future change to the token format (or a session
     * value set by something else) from breaking out of the attribute.
     */
    #[Test]
    public function field_escapes_the_token_rather_than_interpolating_it_raw(): void
    {
        $_SESSION['csrf_token'] = '"><script>alert(1)</script>';

        $html = Csrf::field();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }

    #[Test]
    public function validate_accepts_the_matching_token_from_the_post_body(): void
    {
        $_POST['_csrf'] = Csrf::token();

        $this->assertTrue(Csrf::validate());
    }

    #[Test]
    public function validate_accepts_the_matching_token_from_the_ajax_header(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();

        $this->assertTrue(Csrf::validate());
    }

    #[Test]
    public function validate_rejects_a_wrong_token(): void
    {
        Csrf::token();
        $_POST['_csrf'] = str_repeat('a', 64);

        $this->assertFalse(Csrf::validate());
    }

    #[Test]
    public function validate_rejects_a_missing_token(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::validate());
    }

    #[Test]
    public function validate_rejects_an_empty_submitted_token(): void
    {
        Csrf::token();
        $_POST['_csrf'] = '';

        $this->assertFalse(Csrf::validate());
    }

    /**
     * The dangerous case: no token in the session at all. An empty-vs-empty
     * comparison must not be treated as a match, or a request made before any
     * form was rendered would sail through.
     */
    #[Test]
    public function validate_rejects_everything_when_the_session_has_no_token(): void
    {
        $_POST['_csrf'] = '';
        $this->assertFalse(Csrf::validate());

        $_POST['_csrf'] = str_repeat('b', 64);
        $this->assertFalse(Csrf::validate());
    }

    /**
     * A truncated prefix of the real token must fail. This is what hash_equals
     * buys over ===; a length-only or short-circuiting comparison would leak
     * how much of a guess was correct.
     */
    #[Test]
    public function validate_rejects_a_prefix_of_the_real_token(): void
    {
        $token = Csrf::token();
        $_POST['_csrf'] = substr($token, 0, 32);

        $this->assertFalse(Csrf::validate());
    }

    #[Test]
    public function validate_rejects_a_non_string_submission(): void
    {
        Csrf::token();
        $_POST['_csrf'] = ['not', 'a', 'string'];

        $this->assertFalse(Csrf::validate());
    }
}
