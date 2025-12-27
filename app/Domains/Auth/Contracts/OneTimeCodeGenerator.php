<?php

declare(strict_types=1);

namespace App\Domains\Auth\Contracts;

interface OneTimeCodeGenerator
{
    public function __invoke(int $digits): string;
}
