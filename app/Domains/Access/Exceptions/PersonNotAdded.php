<?php

declare(strict_types=1);

namespace App\Domains\Access\Exceptions;

use App\Domains\Access\Actions\AddPersonToRole;
use App\Domains\Access\Models\Role;
use RuntimeException;

/**
 * Why {@see AddPersonToRole} didn't add someone. The message is shown to the
 * administrator who added them, in the list of people who weren't added.
 */
final class PersonNotAdded extends RuntimeException
{
    public static function blank(): self
    {
        return new self('There\'s no NetID or email address.');
    }

    public static function notInDirectory(string $person): self
    {
        return new self("No one with the NetID or email address {$person} is in the Northwestern Directory.");
    }

    public static function noLocalAccounts(string $email): self
    {
        return new self("{$email} isn't in the Northwestern Directory, and email-code sign-in is off, so there's no local account to create.");
    }

    public static function deactivated(string $person): self
    {
        return new self("The account for {$person} is deactivated. Reactivate it on their page in Administration first.");
    }

    public static function apiUser(string $person): self
    {
        return new self("{$person} is an API user. Assign API users roles one at a time.");
    }

    public static function yourself(): self
    {
        return new self('You can\'t add yourself to a role.');
    }

    public static function alreadyAssigned(string $person, Role $role): self
    {
        return new self("{$person} already has the {$role->name} role.");
    }
}
