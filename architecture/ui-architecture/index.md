# UI Architecture

The starter has **one frontend stack**: [Filament](https://filamentphp.com/), built on Livewire, Alpine and Tailwind CSS. Every page, from sign-in to error pages, uses it or the Northwestern design tokens it is built on. Northwestern branding comes from the [Northwestern Filament Theme](https://github.com/NIT-Administrative-Systems/northwestern-filament-theme), which follows the university’s Department Templates 4.0.

## Where pages live

| Surface                                                                                                    | URL                                                               | Built with                                            | Use it for                                                                                                              |
| ---------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| [**App panel**](https://laravel-starter.entapp.northwestern.edu/building/app-panel/)                       | `/app`                                                            | Filament panel (`AppPanelProvider`)                   | Everything your application does for its users. It is the default panel, and it owns sign-in for the whole application. |
| [**Administration panel**](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/) | `/administration`                                                 | Filament panel (`AdministrationPanelProvider`)        | Back-office tooling: users and roles, API access, announcements, audits, support tickets and the platform overview.     |
| [**Public layout**](https://laravel-starter.entapp.northwestern.edu/building/public-pages/)                | anywhere outside the panels, such as `/` and `/support/changelog` | `<x-layouts.public>` with Filament’s Blade components | Pages people can see without signing in.                                                                                |
| [**Error layout**](https://laravel-starter.entapp.northwestern.edu/building/error-pages/)                  | 500, 503 and database-paused pages                                | `<x-layouts.error>`                                   | Errors where the application may not be working. It renders without Filament, auth or the database.                     |
| [**Mail**](https://laravel-starter.entapp.northwestern.edu/building/branding-and-mail/#email)              | —                                                                 | Laravel Markdown mail, with the starter’s theme       | Email.                                                                                                                  |

> **Tip**
>
> Build features in the **app panel**. Reach for the public layout only for pages that must work without signing in.

## The app panel

The app panel at `/app` is where an application built on the starter does its work. Any signed-in user can open it, except API users, and Resources, Pages, Clusters and Widgets in `app/Filament/App/` are discovered automatically. See [The App Panel](https://laravel-starter.entapp.northwestern.edu/building/app-panel/) for its dashboard, Account area, Help menu, notifications and simple pages.

## The administration panel

The administration panel at `/administration` holds the starter’s back-office tools: users and roles, the API’s credentials and request log, MCP clients, announcements, audit logs, sign-in records, support tickets, the platform overview and links to developer tools. It requires the `AccessAdministrationPanel` permission, and each tool checks its own permission as well. Its resources and pages live in `app/Filament/Administration/`, and it has no footer. See [The Administration Panel](https://laravel-starter.entapp.northwestern.edu/building/administration-panel/) for each tool, who can use it, and how to add your own.

## The public layout

`<x-layouts.public>` is the layout for pages outside the panels, such as the landing page and the changelog. Its routes run in the app panel’s context, so it can use Filament’s Blade components, and it is light only. See [Public Pages](https://laravel-starter.entapp.northwestern.edu/building/public-pages/) for adding a page, and [Error Pages](https://laravel-starter.entapp.northwestern.edu/building/error-pages/) for the error pages that also use it.

The theme, footer and email are covered in [Branding & Mail](https://laravel-starter.entapp.northwestern.edu/building/branding-and-mail/).

***

## Filament Architecture

Filament is structured around **Panels** and **Resources**.

### Filament Panels

A **Panel** is a discrete, isolated area of the site with its own navigation, set of resources, and pages.

> **Example:** Everything under `/app` is driven by the `AppPanelProvider`, and everything under `/administration` by the `AdministrationPanelProvider`.

#### Panel ID & Authorization

Each panel has an **ID**, which is also the key for authorization:

app/Providers/Filament/AppPanelProvider.php

```php
class AppPanelProvider extends PanelProvider
{
    public const string ID = 'app';


    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id(self::ID)
            // . . .
    }
}
```

The access check for an entire panel is `User::canAccessPanel()`, which matches on the panel ID. A panel that isn’t listed there throws, so a new panel can’t be opened by accident:

```php
return match ($panel->getId()) {
    AppPanelProvider::ID => ! $this->is_api_user,
    AdministrationPanelProvider::ID => $this->can(SystemPermission::AccessAdministrationPanel),
};
```

> **Testing administration pages**
>
> `app` is the default panel. Livewire tests for administration pages must set the panel first: `Filament::setCurrentPanel(AdministrationPanelProvider::ID)`.

### Filament Resources

Filament adapts Eloquent Models to its system using *Resources*. This is where the configuration for the tables, filters, and CRUD screens resides.

> **Filament Resources vs. Eloquent Resources**
>
> Filament Resources are a different concept from Laravel’s built-in [Eloquent Resources](https://laravel.com/docs/13.x/eloquent-resources). They are primarily for UI definition.

#### Generating Resources

To ensure correct mapping to the application’s domain structure, you must specify the model namespace when generating a resource:

```bash
php artisan make:filament-resource User --generate --model-namespace=App\\Domains\\User\\Models
```

The `--generate` flag is highly recommended as it uses model introspection to create reasonable defaults for forms and columns.

Filament’s generators target the default panel, which is the app panel. For an administration resource or page, add `--panel=administration`:

```bash
php artisan make:filament-resource User --generate --model-namespace=App\\Domains\\User\\Models --panel=administration
```

#### Relation Managers

To facilitate relationships between resources (e.g., users belonging to a role), you use *Relation Managers*. You must specify the related model’s full path, and the panel when the resource is not in the app panel. `RoleResource` is in the administration panel:

```bash
# Example: Generating a manager for the Role::users() relationship
php artisan make:filament-relation-manager --related-model=App\\Domains\\User\\Models\\User --attach --panel=administration Role users username
```
