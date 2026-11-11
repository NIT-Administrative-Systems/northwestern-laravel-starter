<?php

declare(strict_types=1);

namespace App\Domains\Api\Enums;

/**
 * What someone does with a credential. Issuing covers creating a
 * token, registering an application or service client, and a
 * person consenting to connect one. Modifying one covers
 * rotating, regenerating a secret and editing, while
 * revoking includes disconnecting an application.
 */
enum CredentialOperation
{
    case See;
    case Issue;
    case Modify;
    case Revoke;
    case Use;
}
