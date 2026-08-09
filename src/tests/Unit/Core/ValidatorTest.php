<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    #[Test]
    public function returns_no_errors_when_every_required_field_is_present(): void
    {
        $errors = Validator::required(
            ['name' => 'Acme', 'email' => 'a@example.test'],
            ['name', 'email']
        );

        $this->assertSame([], $errors);
    }

    #[Test]
    public function reports_a_missing_key(): void
    {
        $errors = Validator::required(['name' => 'Acme'], ['name', 'email']);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayNotHasKey('name', $errors);
        $this->assertSame('Email is required.', $errors['email']);
    }

    /**
     * The reason required() trims: a form posts every field it renders, so an
     * untouched text input arrives as '' and a space-bar entry as ' '. Neither
     * is a value.
     */
    #[Test]
    public function treats_empty_and_whitespace_only_values_as_missing(): void
    {
        $errors = Validator::required(
            ['a' => '', 'b' => '   ', 'c' => "\t\n"],
            ['a', 'b', 'c']
        );

        $this->assertCount(3, $errors);
    }

    #[Test]
    public function treats_null_as_missing(): void
    {
        $this->assertArrayHasKey('name', Validator::required(['name' => null], ['name']));
    }

    /**
     * '0' is a legitimate value — a quantity, a threshold, a price. It must not
     * be swallowed by a loose emptiness check.
     */
    #[Test]
    public function accepts_zero_as_a_real_value(): void
    {
        $this->assertSame([], Validator::required(['qty' => '0'], ['qty']));
        $this->assertSame([], Validator::required(['qty' => 0], ['qty']));
    }

    #[Test]
    public function only_checks_the_fields_it_was_asked_about(): void
    {
        $errors = Validator::required(['name' => 'Acme', 'extra' => ''], ['name']);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function returns_no_errors_for_an_empty_field_list(): void
    {
        $this->assertSame([], Validator::required([], []));
    }
}
