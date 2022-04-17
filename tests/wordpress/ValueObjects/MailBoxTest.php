<?php

declare(strict_types=1);

namespace Snicco\Component\BetterWPMail\Tests\wordpress\ValueObjects;

use Codeception\TestCase\WPTestCase;
use InvalidArgumentException;
use LogicException;
use Snicco\Component\BetterWPMail\ValueObject\Mailbox;
use WP_UnitTest_Factory;
use WP_User;

use function array_merge;

/**
 * @internal
 */
final class MailBoxTest extends WPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mailbox::$email_validator = null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mailbox::$email_validator = null;
    }

    /**
     * @test
     */
    public function test_from_string(): void
    {
        $address = Mailbox::create('calvin@web.de');
        $this->assertSame('calvin@web.de', $address->address());
        $this->assertSame('calvin@web.de', $address->toString());
        $this->assertSame('', $address->name());

        $address = Mailbox::create('Calvin Alkan <calvin@web.de>');
        $this->assertSame('calvin@web.de', $address->address());
        $this->assertSame('Calvin Alkan <calvin@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan <calvin@web.de>', (string) $address);
        $this->assertSame('Calvin Alkan', $address->name());
    }

    /**
     * @test
     */
    public function test_from_string_throws_exception_for_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[calvin@webde] is not a valid email');
        Mailbox::create('calvin@webde');
    }

    /**
     * @test
     */
    public function test_from_string_throws_exception_for_bad_pattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[Calvin <calvin@webde] is not a valid address');
        Mailbox::create('Calvin <calvin@webde');
    }

    /**
     * @test
     */
    public function test_from_array_with_names_keys(): void
    {
        $address = Mailbox::create([
            'name' => 'Calvin Alkan',
            'email' => 'c@web.de',
        ]);
        $this->assertSame('c@web.de', $address->address());
        $this->assertSame('Calvin Alkan <c@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan', $address->name());

        $address = Mailbox::create([
            'email' => 'c@web.de',
            'name' => 'Calvin Alkan',
        ]);
        $this->assertSame('c@web.de', $address->address());
        $this->assertSame('Calvin Alkan <c@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan', $address->name());
    }

    /**
     * @test
     */
    public function test_from_array_with_numerical_keys(): void
    {
        $address = Mailbox::create(['c@web.de', 'Calvin Alkan']);
        $this->assertSame('c@web.de', $address->address());
        $this->assertSame('Calvin Alkan <c@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan', $address->name());
    }

    /**
     * @test
     */
    public function test_from_array_with_numerical_keys_must_have_email_part_first(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[calvin alkan] is not a valid email.');

        Mailbox::create(['Calvin Alkan', 'c@web.de']);
    }

    /**
     * @test
     */
    public function test_from_wp_user_with_first_name_and_last_name(): void
    {
        $admin = $this->createAdmin([
            'first_name' => 'Calvin',
            'last_name' => 'Alkan',
            'user_email' => 'c@web.de',
        ]);

        $address = Mailbox::create($admin);

        $this->assertSame('c@web.de', $address->address());
        $this->assertSame('Calvin Alkan <c@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan', $address->name());

        $admin = $this->createAdmin([
            'first_name' => 'Marlon',
            'user_email' => 'm@web.de',
        ]);

        $address = Mailbox::create($admin);

        $this->assertSame('m@web.de', $address->address());
        $this->assertSame('Marlon <m@web.de>', $address->toString());
        $this->assertSame('Marlon', $address->name());
    }

    /**
     * @test
     */
    public function test_from_wp_user_with_only_display_name(): void
    {
        $admin = $this->createAdmin([
            'display_name' => 'Calvin Alkan',
            'user_email' => 'c@web.de',
        ]);

        $address = Mailbox::create($admin);

        $this->assertSame('c@web.de', $address->address());
        $this->assertSame('Calvin Alkan <c@web.de>', $address->toString());
        $this->assertSame('Calvin Alkan', $address->name());
    }

    /**
     * @test
     * @psalm-suppress InvalidScalarArgument
     */
    public function test_exception_if_no_valid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$address has to be string,array or an instance of WP_User. Got [integer].');

        Mailbox::create(1);
    }

    /**
     * @test
     */
    public function test_custom_validation_function_can_be_set(): void
    {
        Mailbox::$email_validator = fn (string $email): bool => 'calvin@web.de' === $email;

        // ok
        Mailbox::create('calvin@web.de');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[marlon@web.de] is not a valid email.');

        Mailbox::create('marlon@web.de');
    }

    /**
     * @test
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    public function test_exception_if_validation_function_does_not_return_bool(): void
    {
        Mailbox::$email_validator = function (): void {
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MailBox::email_validator did not return a boolean for address [marlon@web.de].');

        Mailbox::create('marlon@web.de');
    }

    private function createAdmin(array $data): WP_User
    {
        /** @var WP_UnitTest_Factory $factory */
        $factory = $this->factory();

        $user = $factory->user->create_and_get(array_merge($data, [
            'role' => 'administrator',
        ]));

        if (! $user instanceof WP_User) {
            throw new InvalidArgumentException('Must be WP_USER');
        }

        return $user;
    }
}
