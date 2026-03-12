<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use InvalidArgumentException;

enum DirectorySearchType: string
{
    case Email = 'mail';
    case NetId = 'netid';
    case EmployeeId = 'emplid';

    /**
     * Determines the search type based on the input value.
     *
     * @throws InvalidArgumentException
     */
    public static function fromSearchValue(string $searchValue): self
    {
        $trimmedValue = trim($searchValue);

        if (blank($trimmedValue)) {
            throw new InvalidArgumentException('Search value cannot be empty');
        }

        if (filter_var($trimmedValue, FILTER_VALIDATE_EMAIL)) {
            return self::Email;
        }

        if (ctype_digit($trimmedValue)) {
            return self::EmployeeId;
        }

        return self::NetId;
    }
}
