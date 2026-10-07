# Domain-Driven Design

The starter follows Domain-Driven Design (DDD) principles to organize code around business domains rather than technical layers.

## Directory Structure

* app

  * Console
    * Commands/
      * …

  * Domains

    * Access

      * Enums/
        * …
      * Http/
        * …
      * Models/
        * …
      * Policies/
        * …
      * Seeders/
        * …

    * Api

      * Actions/
        * …
      * Enums/
        * …
      * Http/
        * …
      * Models/
        * …
      * Passport/
        * …
      * …

    * Auth

      * Actions/
        * …
      * Enums/
        * …
      * Http/
        * …
      * Models/
        * …
      * …

    * Core

      * Casts/
        * …
      * Data/
        * …
      * Enums/
        * …
      * Exceptions/
        * …
      * Health/
        * …
      * Models/
        * …
      * Services/
        * …
      * …

    * Support

      * Actions/
        * …
      * Enums/
        * …
      * Gateways/
        * …
      * Models/
        * …
      * Repositories/
        * …
      * Seeders/
        * …
      * …

    * User

      * Actions/
        * …
      * Enums/
        * …
      * Models/
        * …
      * Policies/
        * …
      * QueryBuilders/
        * …
      * …

  * Filament

    * Administration/ the administration panel

      * Clusters/
        * …
      * Pages/
        * …
      * Resources/
        * …
      * …

    * App/ the app panel

      * Pages/
        * …
      * Starter/Pages/
        * …
      * …

    * Support/ helpers both panels use
      * …

  * Http/

    * Controllers/
      * …
    * Livewire/
      * …
    * Middleware/
      * …

  * Mcp/ the MCP server and its tools
    * …

  * Providers/
    * …

  * View/
    * Components/
      * …

## Domain Organization

### Core Domain

The **Core** domain contains foundational functionality that applies across the entire application:

**Location:** `app/Domains/Core/`

**Responsibilities:**

* `BaseModel`, which models extend. Model concerns shared with other applications, such as `PrunesAfterRetentionPeriod` for records that expire and `RecordsCustomAudits` for audit events a model’s own changes don’t capture (which the starter’s `RecordsAuditEvents` builds on), come from [Chassis](https://laravel-starter.entapp.northwestern.edu/reference/chassis/)
* The typed preferences base class (`Data/Preferences`), which `UserPreferences` extends
* The audit log: the `Audit` model, the `AuditEvent` enum that names every event, and `RecordsAuditEvents` for recording one
* Filament’s table `Export` model, which prunes exports and their files after `platform.retention.exports` days
* The `ExternalService` enum
* Configuration validators (`Services/ConfigValidation/`)
* Shared exceptions (`NoRollbackException`, `ServiceDownError`, `SentryExceptionHandler`)
* Custom health checks (`Health/`)
* Shared casts (`MarkdownWithJiraLinksCast`)

**When to add code to Core:**

* Functionality needed by multiple domains
* Infrastructure concerns (logging, caching, etc.)
* Shared enums
* Base classes and traits
* Framework extensions

### Auth Domain

The **Auth** domain handles who someone is: signing in and out.

**Location:** `app/Domains/Auth/`

**Responsibilities:**

* The sign-in methods and the step every sign-in ends with (`SignIn`), and single sign-on through Online Passport or Entra ID
* Email login codes (`LoginCodes`)
* Sign-in-as for seeded users in the `local` environment
* Impersonation and its log

### Access Domain

The **Access** domain handles what someone may do: roles and permissions.

**Location:** `app/Domains/Access/`

**Responsibilities:**

* `SystemPermission`, the single source of truth for permissions, and the system roles
* Roles, role types and permissions, and their seeding
* Recording changes to roles and permissions (`AuditsRoles`, `AuditsPermissions`), and finding which role grants a permission (`TracksPermissionSources`)

### Api Domain

The **Api** domain handles the credentials that call the application:

**Location:** `app/Domains/Api/`

**Responsibilities:**

* Who may see, issue, change, revoke or use each credential (`CredentialAccess`), and every OAuth scope (`ApiScopes`)
* Service clients, personal access tokens, OAuth applications and the connections people make to them
* The OAuth consent screen
* The API’s middleware: authenticating tokens, scopes, rate limits and request logging

### User Domain

The **User** domain handles everything related to user data and management:

**Location:** `app/Domains/User/`

**Responsibilities:**

* User management and profile data
* User segmentation and affiliation
* Directory integration (searching, syncing)
* User login tracking
* Wildcard photo management

### Support Domain

The **Support** domain handles the changelog, support tickets and announcements:

**Location:** `app/Domains/Support/`

**Responsibilities:**

* [Changelog](https://laravel-starter.entapp.northwestern.edu/features/changelog/) entries, synced from `resources/changelogs/` by `ChangelogSeeder`
* [Support tickets](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/) submitted from the Contact Support page
* Ticket gateways (TeamDynamix and mail), with a fallback to mail when the primary gateway fails
* [Announcements](https://laravel-starter.entapp.northwestern.edu/features/announcements/): publishing, audiences, dismissals and notifications

## Component Types

### Actions

**Purpose:** Encapsulate single-responsibility business operations

**Location:** `app/Domains/{Domain}/Actions/`

**Characteristics:**

* Single method, `__invoke()`, usually on a `readonly` class
* Focused on one specific operation
* Testable in isolation
* Resolved from the container: injected into controllers, other actions and Filament action closures, or called with `resolve(SomeAction::class)(...)`
* The rules belong inside: an action that issues, changes or revokes a credential takes the person acting, asks `App\Domains\Api\CredentialAccess` whether they may, and records its audit event when someone else’s credential changes, so every caller gets the same behavior (`CreatePersonalAccessToken`, `DisconnectApplication`, `RegisterOAuthApplication`). Only seeders and scheduled sweeps pass no one

**Example:**

```php
namespace App\Domains\User\Actions;


class DetermineUserSegment
{
    public function __invoke(User $user): UserSegment
    {
        // Business logic to determine user segment
    }
}
```

**When to use Actions:**

* Complex business logic that doesn’t belong in a model
* Operations that span multiple models
* Reusable operations called from multiple places
* Operations that need to be tested in isolation

### Enums

**Purpose:** Define sets of related constants with behavior

**Location:** `app/Domains/{Domain}/Enums/`

**Characteristics:**

* Backed by strings or integers
* Can have methods for labels, descriptions, behavior
* Type-safe throughout the application

**Example:**

```php
namespace App\Domains\Auth\Enums;


use Filament\Support\Contracts\HasLabel;


enum AuthType: string implements HasLabel
{
    case SSO = 'sso';
    case Local = 'local';
    case API = 'api';


    public function getLabel(): string
    {
        return match ($this) {
            self::SSO => 'NetID',
            self::Local => 'Verification Code',
            self::API => 'API',
        };
    }
}
```

> **Tip**
>
> For enums that will be represented in the Filament UI, consider implementing [available interfaces](https://filamentphp.com/docs/5.x/advanced/enums) like `HasLabel` for built-in integration.

### Models

**Purpose:** Represent database tables and their relationships

**Location:** `app/Domains/{Domain}/Models/`

**Characteristics:**

* Extend `BaseModel`, except where a package needs to own the parent class: `User` extends `Authenticatable`, `Role` and `Permission` extend Spatie’s, and `OAuthClient` and `OAuthToken` extend Passport’s. `ApiRequestLog` extends Eloquent’s `Model` directly, so the request log isn’t audited.
* Include relationships, scopes, and accessors

**Base Model:**

All models should extend `BaseModel` which provides:

* Automatic audit logging
* Shared scopes and utilities

### Services

**Purpose:** Complex operations or integrations with external systems

**Location:** `app/Domains/{Domain}/Services/`

**Characteristics:**

* More complex than Actions
* Often stateful
* Handle external integrations
* May coordinate multiple Actions

**When to use Services:**

* External API integrations
* Complex calculations or transformations
* Operations requiring significant setup

### Query Builders

**Purpose:** Encapsulate complex queries and scopes

**Location:** `app/Domains/{Domain}/QueryBuilders/`

**Characteristics:**

* Extend Eloquent’s query builder
* Provide fluent, chainable query methods
* Keep query logic out of controllers or models

**Example:**

```php
namespace App\Domains\User\QueryBuilders;


use App\Domains\Auth\Enums\AuthType;
use Illuminate\Database\Eloquent\Builder;


/**
 * @template TModel of User
 *
 * @extends Builder<TModel>
 */
class UserBuilder extends Builder
{
    public function sso(): self
    {
        return $this->where('auth_type', AuthType::SSO);
    }


    public function whereEmailEquals(string $email): self
    {
        $normalized = strtolower(trim($email));


        return $this->where('email', 'ilike', $normalized);
    }
}
```

## Adding New Domains

As your application grows, you may want to add new domains. For example, if building a course management system, you might add:

* app/Domains/Course/

  * Actions/

    * EnrollStudent.php
    * UnenrollStudent.php

  * Enums/
    * CourseStatus.php

  * Models/

    * Course.php
    * Enrollment.php
    * Section.php

  * Services/
    * CourseRegistrationService.php

**Guidelines for new domains:**

1. **Domain should represent a business concept** - Not a technical layer
2. **Domain should have clear boundaries** - Minimal coupling with other domains
3. **Domain should be cohesive** - All code in domain relates to same concept
