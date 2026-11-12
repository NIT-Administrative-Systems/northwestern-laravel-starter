# The Administration Panel

The administration panel at `/administration` holds the starter’s back-office tools. Opening it needs the `AccessAdministrationPanel` permission, and each tool checks its own permission as well, so a role can give someone just the tools they need. Its resources, pages, clusters and widgets live in `app/Filament/Administration/` and are discovered automatically.

> **Tip**
>
> Build your application’s features in the [app panel](https://laravel-starter.entapp.northwestern.edu/building/app-panel/). The administration panel is for the people who run the application.

## Navigation

The sidebar starts with the panel’s name, “Administration”, so a phone says which panel you are in, and it collapses to icons on a desktop. The top bar has the same Help menu as the app panel, and the user menu has **Back to {app name}** and **Account** above Sign Out.

The sidebar groups the tools with the `App\Filament\Administration\Navigation\AdministrationNavGroup` enum: **User Management**, **Platform** and **Developer Tools**. Give a resource or page its group with `protected static string|UnitEnum|null $navigationGroup = AdministrationNavGroup::Platform;`.

| Tool                                                                                                              | Group                | Needs                                                      |
| ----------------------------------------------------------------------------------------------------------------- | -------------------- | ---------------------------------------------------------- |
| [Overview](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#the-overview)           | (top of the sidebar) | `ManageAll`                                                |
| [Users](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#users)                     | User Management      | `ViewUsers`, or `ManageApiAccess` for API users only       |
| [Roles](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#roles)                     | User Management      | `ViewRoles`                                                |
| [Announcements](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#announcements)     | Platform             | `ManageAnnouncements`                                      |
| [API](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#the-api-cluster)             | Platform             | `ManageApiAccess`, or `ViewApiRequestLogs` with the API on |
| [Audit Logs](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#audit-logs)           | Platform             | `ViewAuditLogs`                                            |
| [Sign-In Records](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#sign-in-records) | Platform             | `ViewLoginRecords`                                         |
| [Support Tickets](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#support-tickets) | Platform             | `ViewSupportTickets`, with Contact Support on              |
| [Developer tools](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/#developer-tools) | Developer Tools      | `ManageAll`                                                |

## The Overview

`/administration` opens on **Overview** (`App\Filament\Administration\Pages\Platform\Overview`), which extends Filament’s `Dashboard`. It shows the environment, services, feature flags, health check results, queues, scheduled tasks, sign-in activity and API traffic in one place, and it’s read-only. See [Health Checks](https://laravel-starter.entapp.northwestern.edu/features/health-checks/).

Only someone with `ManageAll` sees it. Anyone else who opens `/administration` starts on the first page in their sidebar, and gets a 403 only when their sidebar is empty. Add your own sections to `resources/views/filament/pages/platform/overview.blade.php`.

## Tables

Every table in both panels shares the defaults in `FilamentServiceProvider`: below `sm` each row becomes a card with a label on every value (`stackedOnMobile()`), filters, sort and search are kept in the session so they survive opening a record and coming back, and the empty state uses a neutral inbox icon.

Each administration table marks its secondary columns with `visibleFrom('lg')`, `visibleFrom('xl')` or `visibleFrom('2xl')`, so it fits its container at 1440 pixels and keeps the columns that matter at 1024. The log tables (Audit Logs, Sign-In Records, API Requests and Role Activity) load after the page appears (`deferLoading()`). Audit Logs, API Requests, Role Activity and a role’s Definition History fold their filters away above the table (`FiltersLayout::AboveContentCollapsible`). Give a new table the same treatment: decide which three or four columns matter at 1024 pixels and put the rest behind a breakpoint.

## Users

**Users** lists everyone, with filters for sign-in method, affiliation and role. Someone with `ManageApiAccess` but not `ViewUsers` sees only API users, whose service clients they manage. The **Add User** menu creates one person:

* **Add Northwestern User** looks a person up in Directory Search by NetID or email and creates them. Needs `CreateUsers`.
* **Add Local User** creates a person who signs in with an email verification code. Needs `CreateUsers`. See [Creating Local Users](https://laravel-starter.entapp.northwestern.edu/features/authentication/#creating-local-users).
* **Add API User** creates an API user with its first service client. Needs `ManageApiAccess`, with the API on. See [API System](https://laravel-starter.entapp.northwestern.edu/features/api/).

The **Add People to a Role** menu gives a role to a pasted list of NetIDs and email addresses, or the people in a CSV file, creating the accounts that don’t exist yet. Needs `AssignRoles` and `CreateUsers`. See [Adding People in Bulk](https://laravel-starter.entapp.northwestern.edu/features/authorization/#adding-people-in-bulk).

The **Trashed** filter finds deactivated accounts.

A person’s record has four pages, in sub-navigation across the top: **Details**, **Access**, **Activity** and **API Credentials**. An API user’s record puts **API Credentials** second, since that is what an administrator opens it for. Each page shows a tab for each of these that applies to the person and that the viewer holds the permission for, and a page with no tabs is left out:

| Page            | Tab                    | Shows                                                        | Needs                                                  |
| --------------- | ---------------------- | ------------------------------------------------------------ | ------------------------------------------------------ |
| Access          | Roles                  | Their roles, which can be assigned and removed here          | Viewing the person; changing roles needs `AssignRoles` |
| Access          | Role History           | Every role assignment and removal                            | `ViewAuditLogs`, for people other than API users       |
| Activity        | Audit Logs             | Every audited change to them                                 | `ViewAuditLogs`, for people other than API users       |
| Activity        | Sign-In Records        | Their sign-ins                                               | `ViewLoginRecords`, for people other than API users    |
| API Credentials | Service Clients        | An API user’s clients: create, rotate, restrict and revoke   | `ManageApiAccess`, for API users                       |
| API Credentials | Personal Access Tokens | Their tokens, which can be revoked                           | `ManageApiAccess`, for people other than API users     |
| API Credentials | Connected Applications | Their applications and AI clients, which can be disconnected | `ManageApiAccess`, for people other than API users     |
| API Credentials | API Requests           | An API user’s request log                                    | `ViewApiRequestLogs`, for API users with logging on    |

The pages are `ManageUserAccess`, `ManageUserActivity` and `ManageUserApiCredentials` in `app/Filament/Administration/Resources/Users/Pages/`, and `UserResource::getRecordSubNavigation()` orders them. To add a tab, list its relation manager in the page’s `managers()`.

These tabs stay while the API or MCP is off, so an administrator can still revoke what exists; creating, rotating and editing wait until the feature is back on. Who may do what with each credential is decided by `CredentialAccess` (see [API Operations](https://laravel-starter.entapp.northwestern.edu/features/api-operations/#when-the-starter-refuses)).

The **Impersonate** button signs you in as the person and opens the app panel; leaving impersonation brings you back to their page. See [Impersonating Users](https://laravel-starter.entapp.northwestern.edu/features/authentication/#impersonating-users).

**Deactivate** signs the person out everywhere, revokes their credentials and keeps them from signing in; **Reactivate** lets them sign in again, and nothing else does: signing in, a directory sync, **Add Northwestern User** and adding people in bulk all leave a deactivated account deactivated. Both need `EditUsers`. See [Deactivating Accounts](https://laravel-starter.entapp.northwestern.edu/features/authentication/#deactivating-accounts).

## Roles

**Roles** lists every role with its type and how many people hold it. A role’s page shows its name, type, how many people hold it and the permissions it grants, with personal permissions marked, and an **Assigned Users** tab for assigning and removing people, one at a time or [in bulk](https://laravel-starter.entapp.northwestern.edu/features/authorization/#adding-people-in-bulk). Its **Definition History** page shows every change to its definition, with what changed. Creating and editing roles need `EditRoles`, deleting them `DeleteRoles`, and assigning them `AssignRoles`. Nobody can give themselves a role, from either the role’s tab or their own record, Super Administrators included. See [Authorization](https://laravel-starter.entapp.northwestern.edu/features/authorization/).

The **Role Activity** button on the Roles page lists role assignments and removals across everyone, with where each came from. It needs `ViewAuditLogs`.

## The API Cluster

**API** groups the API’s tools under one sidebar entry, each page with its own rule:

* **API Overview**, where the cluster opens, shows API users, clients, expiring secrets and the last day’s traffic. It needs `ManageApiAccess`, or `ViewApiRequestLogs` with the API on.
* **API Requests** is the request log, with its charts. Turn on the **Live** filter to refresh it every 30 seconds. It needs `ViewApiRequestLogs`, with the API and request logging on. See [Request Logging](https://laravel-starter.entapp.northwestern.edu/features/api/#request-logging).
* **Applications** registers and manages OAuth applications. It needs `ManageApiAccess`; while the API is off it lists and revokes them but can’t register or edit. See [Connected Applications](https://laravel-starter.entapp.northwestern.edu/features/api/#connected-applications).
* **MCP Clients** lists the MCP clients, whether they registered themselves or came from a client metadata document, and revokes them. It needs `ManageApiAccess`, whether or not MCP is on. See [MCP](https://laravel-starter.entapp.northwestern.edu/features/mcp/#managing-clients).

## Announcements

**Announcements** writes, publishes, ends, shows again and duplicates the announcements people see in the banner and on the Announcements page. See [Announcements](https://laravel-starter.entapp.northwestern.edu/features/announcements/).

## Audit Logs

**Audit Logs** lists every audited change across the application, filterable by event, record type and date, with a diff of each change on its page. See [Audit Logging](https://laravel-starter.entapp.northwestern.edu/features/audit-logging/).

## Sign-In Records

**Sign-In Records** lists every sign-in with the person’s user segment, with charts of sign-ins over time and by segment. See [User Segmentation](https://laravel-starter.entapp.northwestern.edu/features/authentication/#user-segmentation).

## Support Tickets

**Support Tickets** is a read-only log of Contact Support submissions and where each was delivered. It shows only while Contact Support is on. See [Support Tickets](https://laravel-starter.entapp.northwestern.edu/features/support-tickets/).

## Developer Tools

Links to Telescope, to the RustFS console when `S3_CONSOLE_URL` is set, and to MailPit when `MAIL_CAPTURE_URL` is set. They show only to people who can view Telescope (`ManageAll`) and open in a new tab.

## Adding a Tool

1. **Generate it for this panel.** Filament’s generators target the app panel unless you pass `--panel=administration`, for example `php artisan make:filament-resource Course --panel=administration`.
2. **Gate it.** Give the model a policy whose `viewAny` checks a permission, or give a page a `canAccess()`. Add a `SystemPermission` case for a new kind of access and grant it through a role. Both panels use Filament’s strict authorization, so a resource whose model has no policy, or a policy without an ability a page or table asks for (`view` for a table’s rows, say), throws instead of letting everyone in. A resource that `canAccess()` alone decides overrides `canViewAny()` to return it, as the MCP client and application resources do.
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

* `->mountUsing($secret->mountFresh())`, which gives each run its own key, so a cancelled run, or one open in another browser tab, never shows its credential to another;
* `$secret->issueOnce($livewire, fn () => ['id' => ..., 'secret' => ...])` in the first step, so stepping back and forward doesn’t issue a second credential;
* `$secret->identifierEntry('client_id', 'Client ID')` and `$secret->secretEntry('client_secret', 'Client Secret')` in the copy step, which show the value in full, wrapped, with a **Copy** button under it;
* `->modifyWizardUsing($secret->hideBackOnceIssued())`, so there is no **Back** to a form whose credential already exists;
* `->modalSubmitAction($secret->savedAction())`, which labels the last button **I’ve Saved It**;
* `->action(fn (HasActions $livewire) => $secret->forget($livewire))` on submit.

Its other methods also take the Livewire component (`$livewire`, or `$this` on a page), which holds the run’s key in the mounted action’s arguments. The secret is kept encrypted in the session between the steps, never in Livewire state.

The panel’s Tailwind theme is `resources/css/filament/administration/theme.css`. In tests, call `Filament::setCurrentPanel(AdministrationPanelProvider::ID)` before testing one of its Livewire pages, because the app panel is the default.
