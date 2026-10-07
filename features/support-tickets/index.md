# Support Tickets

The starter includes a user-facing contact support form that submits tickets to a configurable backend. It ships with two gateway drivers, **Email** and **TeamDynamix**, and an automatic email fallback that ensures user requests are never lost.

The feature is disabled by default and can be enabled with a single environment variable.

## Overview

`CreateSupportTicket` is the whole submission: give it the person and the form’s fields, and it saves the ticket, sends it to the configured ticket system, records the result and falls back to email when needed. Behind it:

* **Contract** - the `TicketSystemGateway` interface, which each ticket system implements
* **Driver** - `SUPPORT_DRIVER` names a `TicketSystem` case, and `SupportServiceProvider` binds that case’s gateway (`TicketSystem::gatewayClass()`)
* **Result** - each gateway returns an immutable `CreationResult` with the outcome

***

## Enabling the Feature

.env

```bash
SUPPORT_ENABLED=true
SUPPORT_DRIVER=mail
SUPPORT_MAIL_TO=your-team@northwestern.edu
```

When enabled, the **Contact Support** page appears in the Help menu and the submission log appears in the administration panel. When disabled, the Help menu hides the link, the page still has a route but returns 403 (`ContactSupport::canAccess()`), and no gateway is bound.

***

## How It Works

1. **User visits the contact form**

   Signed-in users open **Contact Support** in the App panel (`/app/support/contact`) and fill in a subject and details.

2. **Ticket is persisted**

   `CreateSupportTicket` saves the submission to the `support_tickets` table, recording the user’s email at the time of submission for audit purposes.

3. **Primary gateway submits the ticket**

   It sends the ticket to the configured gateway (Email or TeamDynamix). The gateway returns an immutable `CreationResult` indicating success or failure.

4. **Result is recorded**

   It updates the ticket with the gateway’s response: ticket number, system type, and any error message.

5. **Fallback fires if needed**

   If the primary gateway is **not** the email driver and it fails, the system automatically falls back to the `MailGateway` so the user’s request is still delivered. The `fallback_sent_at` timestamp is only set if the fallback actually succeeds.

6. **User receives feedback**

   The `ContactSupport` page sends a Filament notification:

   * **Success** - a success notification with the ticket reference number
   * **Fallback sent** - a success notification that confirms submission without exposing internal details
   * **Both failed** - a danger notification asking the user to try again later

***

## Gateway Drivers

Email

**Default driver**

Sends ticket details to the support team mailbox and a confirmation to the requester.

* Generates `SUP-{id}` style references
* Queued via Laravel’s mail system
* Also serves as the automatic fallback

TeamDynamix

**External ticketing system**

Creates tickets directly in TeamDynamix via the REST API.

* 3-attempt retry with 250ms backoff
* Caches metadata IDs for one week
* Returns native TDX ticket references

### Email Gateway

The email gateway dispatches two queued emails per submission:

1. **Team notification** (`SupportTicketMessage`) - Contains the full request body, submitter metadata, and a fallback warning when operating in fallback mode.
2. **User confirmation** (`SupportTicketConfirmation`) - A user-facing email with the reference number and next-step expectations. No internal details are exposed.

The reference prefix is configurable and only applies to the email gateway. External gateways like TeamDynamix return their own native ticket references.

.env

```bash
SUPPORT_MAIL_TO=your-team@northwestern.edu
SUPPORT_REFERENCE_PREFIX=SUP
```

When the email gateway is used as a fallback after a primary gateway failure, the team notification includes a warning banner instructing the team to check Sentry for the corresponding error.

### TeamDynamix Gateway

The [TeamDynamix](https://services.northwestern.edu/TDNext) (TDX) gateway submits tickets to the TDX REST API using the [`northwestern-sysdev/tdx-php-sdk`](https://github.com/NIT-Administrative-Systems/tdx-php-sdk) package. The SDK handles authentication and token management, and covers a broad surface beyond ticket creation: people/group lookups, service catalog queries, and metadata management. The starter ships `config/team-dynamix.php`, which reads the connection settings below from the environment and replaces the SDK’s own config, so there is nothing to publish.

The gateway resolves metadata IDs (ticket type, form, status, priority, service) through the `TeamDynamixCacheRepository`, which caches name-to-ID lookups for one week to avoid repeated API round-trips:

.env

```bash
SUPPORT_DRIVER=team-dynamix


# TDX SDK credentials
TDX_API_BASE_URL=https://services.northwestern.edu/TDWebApi
TDX_USERNAME=your-service-account
TDX_PASSWORD=your-password
TDX_TICKET_APP_NAME=your-app-name
TDX_CLIENT_APP_NAME=your-client-app


# TDX ticket defaults
TDX_ASSIGNEE_ID=12345
TDX_TICKET_TYPE=Default
TDX_FORM_TYPE="NU Base Service Request"
TDX_TICKET_STATUS=New
TDX_TICKET_PRIORITY="Low (P4)"
TDX_SERVICE="My Application"
```

> **Caution**
>
> `TDX_ASSIGNEE_ID` is required when using the TeamDynamix driver. This is the numeric ID of the TDX group that tickets should be assigned to, not a user ID.

***

## Automatic Fallback

When the primary gateway is **not** the email driver and it fails, the system dispatches the ticket via the `MailGateway` in fallback mode. This safety net cannot be disabled, ensuring that user requests are never lost.

The fallback only fires when:

1. The primary gateway returned an error (`creationError: true`)
2. The primary gateway is not the email driver itself (to avoid infinite loops)

The `fallback_sent_at` timestamp on the ticket is only set when the fallback succeeds. Both mailables are queued, so success means the fallback mail was queued, not delivered. If queueing fails (for example, the queue connection is down), the timestamp remains `null`, giving administrators a clear signal that neither path accepted the request.

***

## Filament Admin Panel

The `SupportTicketResource` provides a **read-only** view of all submitted tickets in the administration panel, under the **Platform** navigation group. It requires the `ViewSupportTickets` permission and is only visible when `SUPPORT_ENABLED=true`.

The resource displays a red badge on the navigation item showing the count of tickets where `post_error = true`, giving administrators immediate visibility into failed submissions.

Tickets are globally searchable by subject, ticket number, requester email, and submitter name/username.

***

## Contact Form

The contact form is the **Contact Support** page in the app panel (`/app/support/contact`), reached from the Help menu in the header, available to signed-in users when `SUPPORT_ENABLED=true`. It collects a subject and details, and reports the result as a notification.

### Limited Support Warning

In non-production environments, a warning banner is displayed indicating that support is limited and the form may behave differently. This is controlled by:

.env

```bash
# Defaults to true for all non-production environments
SUPPORT_LIMITED_WARNING=false
```

***

## Implementing a Custom Driver

Adding a new gateway requires three steps: add an enum case, implement the interface, and add configuration.

1. **Add a case to `TicketSystem`**

   app/Domains/Support/Enums/TicketSystem.php

   ```php
   enum TicketSystem: string implements HasLabel
   {
       case TeamDynamix = 'team-dynamix';
       case Mail = 'mail';
       case Jira = 'jira'; // New case


       public function getLabel(): string
       {
           return match ($this) {
               self::TeamDynamix => 'TeamDynamix',
               self::Mail => 'Email',
               self::Jira => 'Jira',
           };
       }


       public function gatewayClass(): string
       {
           return match ($this) {
               self::TeamDynamix => TeamDynamixGateway::class,
               self::Mail => MailGateway::class,
               self::Jira => JiraGateway::class,
           };
       }
   }
   ```

   The enum value (e.g. `'jira'`) is what users set in `SUPPORT_DRIVER` and what gets stored in the `ticketing_system` database column.

2. **Create the gateway class**

   Implement the `TicketSystemGateway` interface. Your gateway **must never throw**. Capture errors and return them via `CreationResult`.

   app/Domains/Support/Gateways/Jira/JiraGateway.php

   ```php
   namespace App\Domains\Support\Gateways\Jira;


   use App\Domains\Support\Contracts\TicketSystemGateway;
   use App\Domains\Support\Enums\TicketSystem;
   use App\Domains\Support\Gateways\CreationResult;
   use App\Domains\Support\Models\SupportTicket;
   use Throwable;


   class JiraGateway implements TicketSystemGateway
   {
       public function create(SupportTicket $ticket): CreationResult
       {
           try {
               // Submit to Jira API...
               $issueKey = $this->submitToJira($ticket);


               return new CreationResult(
                   ticketSystemType: TicketSystem::Jira,
                   creationError: false,
                   ticketNumber: $issueKey,
                   errorMessage: null,
               );
           } catch (Throwable $e) {
               report($e);


               return new CreationResult(
                   ticketSystemType: TicketSystem::Jira,
                   creationError: true,
                   ticketNumber: null,
                   errorMessage: $e->getMessage(),
               );
           }
       }
   }
   ```

   > **Caution**
   >
   > The gateway contract requires that implementations **never throw exceptions**. Errors must be captured internally and returned via the `CreationResult` value object. If your gateway throws, the automatic fallback mechanism will not fire and the user’s request may be lost.

3. **Add configuration**

   Add a section for your driver in `config/support.php` and update `.env.example`:

   config/support.php

   ```php
   'jira' => [
       'base_url' => env('JIRA_BASE_URL'),
       'project_key' => env('JIRA_PROJECT_KEY'),
       'issue_type' => env('JIRA_ISSUE_TYPE', 'Task'),
   ],
   ```

   .env

   ```bash
   SUPPORT_DRIVER=jira
   JIRA_BASE_URL=https://your-org.atlassian.net
   JIRA_PROJECT_KEY=SUP
   ```

The factory resolves gateways through the enum, so no additional wiring is needed. The automatic email fallback, admin panel, and submission recording all work with your new driver.

### Alternative: Rebinding the Interface

If you need a completely custom gateway that doesn’t fit the enum-driven pattern, you can bypass the factory entirely by rebinding the `TicketSystemGateway` interface in `AppServiceProvider::boot()`:

app/Providers/AppServiceProvider.php

```php
use App\Domains\Support\Contracts\TicketSystemGateway;


public function boot(): void
{
    $this->app->bind(TicketSystemGateway::class, function () {
        return new MyCustomGateway();
    });
}
```

Bind it in `boot()`, not `register()`. `SupportServiceProvider` is listed after `AppServiceProvider` in `bootstrap/providers.php`, so its `register()` would overwrite a binding made in `AppServiceProvider::register()`. The custom gateway still returns a `CreationResult` with a `TicketSystem` case as its `ticketSystemType`.

***

## Rate Limiting

The contact form uses tiered rate limiting (per-minute, per-hour, and per-day) to allow short bursts while preventing sustained abuse. All limits are per-user.

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-rate-limit-support-contact-per-minute)`RATE_LIMIT_SUPPORT_CONTACT_PER_MINUTE``2`

Submissions per minute

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-rate-limit-support-contact-per-hour)`RATE_LIMIT_SUPPORT_CONTACT_PER_HOUR``5`

Submissions per hour

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-rate-limit-support-contact-per-day)`RATE_LIMIT_SUPPORT_CONTACT_PER_DAY``10`

Submissions per day

The `support:contact` limiter is defined in `RateLimitingServiceProvider`, with its numbers read from `config/rate-limiting.php`. `ContactSupport::send()` enforces it, since a Livewire action does not pass through route middleware.

***

## Environment Variables

General

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-enabled)`SUPPORT_ENABLED``false`

Enable the Contact Support page and the submission log

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-documentation-url)`SUPPORT_DOCUMENTATION_URL`

User documentation linked from the Help menu; the link is hidden while empty. Independent of `SUPPORT_ENABLED`

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-limited-warning)`SUPPORT_LIMITED_WARNING``true (non-production)`

Show limited support warning banner

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-driver)`SUPPORT_DRIVER``mail`

Gateway driver (`mail`, `team-dynamix`)

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-mail-to)`SUPPORT_MAIL_TO``your-team@northwestern.edu`

Support team email address

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-support-reference-prefix)`SUPPORT_REFERENCE_PREFIX``SUP`

Reference prefix for mail gateway (e.g. `SUP-47`)

TeamDynamix

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-api-base-url)`TDX_API_BASE_URL`Required

TDX REST API base URL

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-username)`TDX_USERNAME`Required

TDX service account username

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-password)`TDX_PASSWORD`Required

TDX service account password

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-ticket-app-name)`TDX_TICKET_APP_NAME`Required

TDTickets application where tickets are created (read by `config/team-dynamix.php`)

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-client-app-name)`TDX_CLIENT_APP_NAME`Required

TDClient application where services are managed (read by `config/team-dynamix.php`)

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-assignee-id)`TDX_ASSIGNEE_ID`Required

TDX group ID for ticket assignment (required for TDX driver)

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-ticket-type)`TDX_TICKET_TYPE``Default`

TDX ticket type name

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-form-type)`TDX_FORM_TYPE``NU Base Service Request`

TDX form type name

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-ticket-status)`TDX_TICKET_STATUS``New`

TDX ticket status name

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-ticket-priority)`TDX_TICKET_PRIORITY``Low (P4)`

TDX ticket priority name

[#](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/#prop-tdx-service)`TDX_SERVICE``APP_NAME`

TDX service name (defaults to application name)
