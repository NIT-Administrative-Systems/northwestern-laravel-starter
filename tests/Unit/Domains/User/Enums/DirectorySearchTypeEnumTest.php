<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\User\Enums;

use App\Domains\User\Enums\DirectorySearchTypeEnum;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectorySearchTypeEnum::class)]
class DirectorySearchTypeEnumTest extends TestCase
{
    #[DataProvider('validSearchValuesProvider')]
    public function test_it_detects_search_type(string $searchValue, DirectorySearchTypeEnum $expectedType): void
    {
        $result = DirectorySearchTypeEnum::fromSearchValue($searchValue);

        $this->assertSame($expectedType, $result);
    }

    #[DataProvider('invalidSearchValuesProvider')]
    public function test_invalid_search_values_throw_exception(string $searchValue, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        DirectorySearchTypeEnum::fromSearchValue($searchValue);
    }

    public function test_email_validation_is_strict(): void
    {
        $validEmails = [
            'user@domain.com',
            'test@northwestern.edu',
            'complex.email+tag@example.org',
        ];

        foreach ($validEmails as $email) {
            $result = DirectorySearchTypeEnum::fromSearchValue($email);
            $this->assertSame(DirectorySearchTypeEnum::EMAIL, $result);
        }
    }

    public function test_employee_id_validation(): void
    {
        // Any numeric string is treated as employee ID
        $validIds = ['1', '123', '123456', '1234567', '12345678'];
        foreach ($validIds as $id) {
            $result = DirectorySearchTypeEnum::fromSearchValue($id);
            $this->assertSame(DirectorySearchTypeEnum::EMPLOYEE_ID, $result);
        }
    }

    public function test_netid_validation(): void
    {
        $validNetIds = ['abc', 'abc123', 'user1', 'test', 'a1b2c3d4', 'test-user', 'existing-no-email', 'user!@#'];
        foreach ($validNetIds as $netId) {
            $result = DirectorySearchTypeEnum::fromSearchValue($netId);
            $this->assertSame(DirectorySearchTypeEnum::NETID, $result);
        }
    }

    public function test_whitespace_is_trimmed(): void
    {
        $result = DirectorySearchTypeEnum::fromSearchValue('  abc123  ');
        $this->assertSame(DirectorySearchTypeEnum::NETID, $result);

        $result = DirectorySearchTypeEnum::fromSearchValue('  user@example.com  ');
        $this->assertSame(DirectorySearchTypeEnum::EMAIL, $result);

        $result = DirectorySearchTypeEnum::fromSearchValue('  1234567  ');
        $this->assertSame(DirectorySearchTypeEnum::EMPLOYEE_ID, $result);
    }

    /**
     * @return array<string, array{string, DirectorySearchTypeEnum}>
     */
    public static function validSearchValuesProvider(): array
    {
        return [
            'typical email' => ['example@northwestern.edu', DirectorySearchTypeEnum::EMAIL],
            'another email format' => ['user@domain.com', DirectorySearchTypeEnum::EMAIL],
            'complex email' => ['test.email+tag@example.org', DirectorySearchTypeEnum::EMAIL],
            'employee ID 7 digits' => ['1234567', DirectorySearchTypeEnum::EMPLOYEE_ID],
            'netid alphanumeric' => ['abc123', DirectorySearchTypeEnum::NETID],
            'netid short' => ['abc', DirectorySearchTypeEnum::NETID],
            'netid long' => ['abcd1234', DirectorySearchTypeEnum::NETID],
            'netid with hyphens' => ['test-user', DirectorySearchTypeEnum::NETID],
            'netid longer format' => ['missing-mail-format', DirectorySearchTypeEnum::NETID],
            'netid alphabetic only' => ['notanemail', DirectorySearchTypeEnum::NETID],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidSearchValuesProvider(): array
    {
        return [
            'empty string' => ['', 'Search value cannot be empty'],
            'whitespace only' => ['   ', 'Search value cannot be empty'],
        ];
    }
}
