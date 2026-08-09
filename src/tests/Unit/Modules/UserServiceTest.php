<?php
declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Core\Database;
use App\Modules\Admin\UserRepository;
use App\Modules\Admin\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\NeverConnectingPdo;

#[CoversClass(UserService::class)]
final class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        // UserRepository resolves Database::connection() in its constructor.
        // validateUserInput() never queries.
        Database::swap(new NeverConnectingPdo());
        $this->service = new UserService(new UserRepository());
    }

    protected function tearDown(): void
    {
        Database::swap(null);
    }

    private function validUser(array $overrides = []): array
    {
        return $overrides + [
            'name'     => 'Jordan Lee',
            'email'    => 'jordan@typhoncath.test',
            'password' => 'correct-horse',
            'role_id'  => '3',
        ];
    }

    #[Test]
    public function a_complete_new_user_passes_validation(): void
    {
        $this->assertSame([], $this->service->validateUserInput($this->validUser(), false));
    }

    #[Test]
    public function a_new_user_needs_a_name_and_a_role(): void
    {
        $errors = $this->service->validateUserInput(
            $this->validUser(['name' => '   ', 'role_id' => '']),
            false
        );

        $this->assertContains('Name is required.', $errors);
        $this->assertContains('Role is required.', $errors);
    }

    #[Test]
    #[DataProvider('malformedEmails')]
    public function the_email_must_look_like_an_email(string $email): void
    {
        $errors = $this->service->validateUserInput($this->validUser(['email' => $email]), false);

        $this->assertNotSame([], $errors);
    }

    /** @return array<string, array{string}> */
    public static function malformedEmails(): array
    {
        return [
            'empty'      => [''],
            'no at sign' => ['jordan.typhoncath.test'],
            'no domain'  => ['jordan@'],
            'spaces'     => ['jordan lee@typhoncath.test'],
        ];
    }

    #[Test]
    public function a_new_user_needs_a_password_of_at_least_eight_characters(): void
    {
        $errors = $this->service->validateUserInput($this->validUser(['password' => 'short']), false);
        $this->assertContains('Password must be at least 8 characters.', $errors);

        $missing = $this->service->validateUserInput($this->validUser(['password' => '']), false);
        $this->assertContains('Password must be at least 8 characters.', $missing);

        $this->assertSame([], $this->service->validateUserInput($this->validUser(['password' => '12345678']), false));
    }

    /**
     * The edit form leaves the password box blank to mean "leave it alone".
     * Requiring a password there would force a rotation on every profile edit.
     */
    #[Test]
    public function editing_a_user_without_touching_the_password_is_allowed(): void
    {
        $errors = $this->service->validateUserInput($this->validUser(['password' => '']), true);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function a_password_supplied_during_an_edit_still_has_to_be_long_enough(): void
    {
        $errors = $this->service->validateUserInput($this->validUser(['password' => 'short']), true);

        $this->assertContains('New password must be at least 8 characters.', $errors);
    }

    /**
     * role_id is checked with empty(), so '0' is rejected — which is correct
     * here, since roles.id is AUTO_INCREMENT and never 0.
     */
    #[Test]
    public function a_zero_role_id_is_rejected(): void
    {
        $errors = $this->service->validateUserInput($this->validUser(['role_id' => '0']), false);

        $this->assertContains('Role is required.', $errors);
    }
}
