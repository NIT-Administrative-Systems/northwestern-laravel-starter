# The Administration Panel

The administration panel at `/administration` holds the starter’s back-office tools. Opening it needs the `AccessAdministrationPanel` permission, and each tool checks its own permission as well, so a role can give someone just the tools they need. Its resources, pages, clusters and widgets live in `app/Filament/Administration/` and are discovered automatically.

> **Tip**
>
> Build your application’s features in the [app panel](https://laravel-starter.entapp.northwestern.edu/building/app-panel/). The administration panel is for the people who run the application.

## Navigation

The sidebar groups the tools with the `App\Filament\Administration\Navigation\AdministrationNavGroup` enum: **User Management**, **Platform** and **Developer Tools**. Give a resource or page its group with `protected static string|UnitEnum|null $navigationGroup = AdministrationNavGroup::Platform;`.

| Tool                                                                                                              | Group           | Needs                                                      |
| ----------------------------------------------------------------------------------------------------------------- | --------------- | ---------------------------------------------------------- |
| [Users](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#users)                     | User Management | `ViewUsers`, or `ManageApiAccess` for API users only       |
| [Roles](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#roles)                     | User Management | `ViewRoles`                                                |
| [Announcements](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#announcements)     | Platform        | `ManageAnnouncements`                                      |
| [API](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#the-api-cluster)             | Platform        | `ManageApiAccess`, or `ViewApiRequestLogs` with the API on |
| [Audit Logs](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#audit-logs)           | Platform        | `ViewAuditLogs`                                            |
| [Sign-In Records](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#sign-in-records) | Platform        | `ViewLoginRecords`                                         |
| [Support Tickets](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#support-tickets) | Platform        | `ViewSupportTickets`, with Contact Support on              |
| [Overview](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#platform-overview)      | Platform        | `ManageAll`                                                |
| [Developer tools](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#developer-tools) | Developer Tools | `ManageAll`                                                |

## The Dashboard

`/administration` opens on Filament’s dashboard with a placeholder `WelcomeWidget` (`app/Filament/Administration/Widgets/WelcomeWidget.php`). Replace it with the widgets, stats and charts your administrators need.

## Users

**Users** lists everyone, with filters for sign-in method, affiliation and role. Someone with `ManageApiAccess` but not `ViewUsers` sees only API users, whose service clients they manage. Three actions create people:

* **Add Northwestern User** looks a person up in Directory Search by NetID or email and creates them. Needs `CreateUsers`.
* **Add Local User** creates a person who signs in with an email verification code. Needs `CreateUsers`. See [Creating Local Users](https://laravel-starter.entapp.northwestern.edu/features/authentication/#creating-local-users).
* **Create API User** creates an API user with its first service client. Needs `ManageApiAccess`, with the API on. See [API System](https://laravel-starter.entapp.northwestern.edu/features/api/).

A person’s page shows their details and these tabs, each only when it applies to them and the viewer holds its permission:

| Tab                    | Shows                                                        | Needs                                                  |
| ---------------------- | ------------------------------------------------------------ | ------------------------------------------------------ |
| Roles                  | Their roles, which can be assigned and removed here          | Viewing the person; changing roles needs `AssignRoles` |
| Role History           | Every role assignment and removal                            | `ViewAuditLogs`                                        |
| Audit Logs             | Every audited change to them                                 | `ViewAuditLogs`                                        |
| Login Records          | Their sign-ins                                               | `ViewLoginRecords`                                     |
| Personal Access Tokens | Their tokens, which can be revoked                           | `ManageApiAccess`                                      |
| Connected Applications | Their applications and AI clients, which can be disconnected | `ManageApiAccess`                                      |
| Service Clients        | An API user’s clients: create, rotate, restrict and revoke   | `ManageApiAccess`, for API users                       |
| API Requests           | An API user’s request log                                    | `ViewApiRequestLogs`, for API users with logging on    |

These tabs stay while the API or MCP is off, so an administrator can still revoke what exists; creating, rotating and editing wait until the feature is back on. Who may do what with each credential is decided by `CredentialAccess` (see [API Operations](https://laravel-starter.entapp.northwestern.edu/features/api-operations/#when-the-starter-refuses)).

The **Impersonate** button signs you in as the person. See [Impersonating Users](https://laravel-starter.entapp.northwestern.edu/features/authentication/#impersonating-users).

## Roles

**Roles** lists every role with its type and how many people hold it. A role’s page shows its permissions and an **Assigned Users** tab for assigning and removing people. Its **Definition History** page shows every change to its definition, with what changed. Creating, editing and deleting roles need `EditRoles` and `DeleteRoles`. See [Authorization](https://laravel-starter.entapp.northwestern.edu/features/authorization/).

The **Role Activity** button on the Roles page lists role assignments and removals across everyone, with where each came from. It needs `ViewAuditLogs`.

## The API Cluster

**API** groups the API’s tools under one sidebar entry, each page with its own rule:

* **Overview** shows API users, clients, expiring secrets and the last day’s traffic. It needs `ManageApiAccess`, or `ViewApiRequestLogs` with the API on.
* **API Requests** is the request log, with its charts. It needs `ViewApiRequestLogs`, with the API and request logging on. See [Request Logging](https://laravel-starter.entapp.northwestern.edu/features/api/#request-logging).
* **Applications** registers and manages OAuth applications. It needs `ManageApiAccess`; while the API is off it lists and revokes them but can’t register or edit. See [Connected Applications](https://laravel-starter.entapp.northwestern.edu/features/api/#connected-applications).
* **MCP Clients** lists the clients that registered themselves and revokes them. It needs `ManageApiAccess`, whether or not MCP is on. See [MCP](https://laravel-starter.entapp.northwestern.edu/features/mcp/#managing-clients).

## Announcements

**Announcements** writes, publishes, ends, shows again and duplicates the announcements people see in the banner and on the Announcements page. See [Announcements](https://laravel-starter.entapp.northwestern.edu/features/announcements/).

## Audit Logs

**Audit Logs** lists every audited change across the application, filterable by event, record type and date, with a diff of each change on its page. See [Audit Logging](https://laravel-starter.entapp.northwestern.edu/features/audit-logging/).

## Sign-In Records

**Sign-In Records** lists every sign-in with the person’s user segment, with charts of sign-ins over time and by segment. See [User Segmentation](https://laravel-starter.entapp.northwestern.edu/features/authentication/#user-segmentation).

## Support Tickets

**Support Tickets** is a read-only log of Contact Support submissions and where each was delivered. It shows only while Contact Support is on. See [Support Tickets](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/).

## Platform Overview

**Overview** (`/administration/overview`) shows the environment, services, feature flags, health check results, queues, scheduled tasks, sign-in activity and API traffic in one place. It’s read-only. See [Health Checks](https://laravel-starter.entapp.northwestern.edu/features/health-checks/).

## Developer Tools

Links to Telescope, to the RustFS console when `S3_CONSOLE_URL` is set, and to MailPit when `MAIL_CAPTURE_URL` is set. They show only to people who can view Telescope (`ManageAll`) and open in a new tab.

## Adding a Tool

1. **Generate it for this panel.** Filament’s generators target the app panel unless you pass `--panel=administration`, for example `php artisan make:filament-resource Course --panel=administration`.
2. **Gate it.** Give the model a policy whose `viewAny` checks a permission, or give a page a `canAccess()`. Add a `SystemPermission` case for a new kind of access and grant it through a role.
3. **Place it.** Set its `$navigationGroup` to an `AdministrationNavGroup` case, or add a case for a new group.

> **A cluster's rule only hides its navigation**
>
> Filament checks a cluster’s `canAccess()` when it builds the sidebar, not when someone opens a page inside it. Give every clustered page its own `canAccess()`, as the API cluster’s pages do.

### Exports

Extend `App\Filament\Support\BaseExporter` instead of Filament’s `Exporter`. Declare the columns and `recordNoun()` (what a row is: `'audit record'`, `'user'`), and the base class does the rest:

* Every text cell that a spreadsheet would run as a formula, one starting with `=`, `+`, `-`, `@`, a tab or a carriage return, gets a leading apostrophe. A name like `=HYPERLINK(...)` can’t act on whoever opens the file.
* The completion notification says how many rows were exported and how many failed.

`ExportAction` already has the label, icon, CSV format and S3 disk set in `FilamentServiceProvider`; pass only `->exporter(...)`.

### Showing a Secret Once

A wizard that issues a credential and shows its secret once uses `App\Filament\Support\RevealOnceSecret`, as the service client, application and personal access token wizards do. Name the wizard with `RevealOnceSecret::for('report_feed:create')`, then:

* `->mountUsing($secret->mountFresh())`, so a cancelled run leaves nothing for the next one to show;
* `$secret->issueOnce(fn () => ['id' => ..., 'secret' => ...])` in the first step, so stepping back and forward doesn’t issue a second credential;
* `$secret->identifierEntry()` and `$secret->secretEntry()` in the copy step;
* `->action(fn () => $secret->forget())` on submit.

The secret is kept encrypted in the session between the steps, never in Livewire state.

The panel’s Tailwind theme is `resources/css/filament/administration/theme.css`. In tests, call `Filament::setCurrentPanel(AdministrationPanelProvider::ID)` before testing one of its Livewire pages, because the app panel is the default.
