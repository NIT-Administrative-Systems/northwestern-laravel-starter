<?php

declare(strict_types=1);

namespace App\Domains\User\Enums;

use InvalidArgumentException;

enum DirectorySearchType: string
{
    case EMAIL = 'mail';
    case NETID = 'netid';
    case EMPLOYEE_ID = 'emplid';

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
            return self::EMAIL;
        }

        if (ctype_digit($trimmedValue)) {
            return self::EMPLOYEE_ID;
        }

        return self::NETID;
    }
}
