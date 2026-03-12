<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use Exception;

class NoRollbackException extends Exception
{
    public function __construct()
    {
        parent::__construct('Migration cannot be undone.');
    }
}
