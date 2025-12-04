---
applyTo: "resources/views/**/*.blade.php,resources/views/**/*.php,resources/css/**/*.{css,scss},resources/sass/**/*.scss,resources/**/*.{astro,html}"
---

# Frontend & UI Instructions

## Dual UI Architecture

This application uses **two separate and distinct UI stacks** optimized for different audiences. Understanding which stack to use is critical.

### Northwestern Laravel UI (User-Facing)

**When to use:**

- Public-facing pages and landing pages
- Custom user dashboards and workflows
- Any interface requiring Northwestern branding
- Complex custom experiences needing full design control

**Stack details:**

- **Layout:** Extend `northwestern::purple-container` or other Northwestern layouts
- **Styling:** Bootstrap 5 (utility classes like `btn`, `card`, `row`, `col-*`)
- **Icons:** Font Awesome (`<i class="fas fa-icon">`)
- **Components:** Custom Blade components in `resources/views/components/`

**Example patterns:**

```blade
@extends("northwestern::purple-container")

@section("content")
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h1 class="h3">Title</h1>
                    <p class="lead">Content</p>
                    <button class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
```

**Available custom components:**

- `<x-breadcrumbs :breadcrumbs="$array">` - Bootstrap breadcrumb navigation
- `<x-clipboard text="...">` - Copy-to-clipboard with feedback
- `<x-default-profile-photo>` - Placeholder profile images
- `<x-not-yet-implemented>` - Placeholder for WIP features

### Filament (Administration Interface)

**When to use:**

- Staff/developer admin panels (`/administration`)
- CRUD operations and data management
- Analytics dashboards and reporting
- User/system configuration
- Internal tooling

**Stack details:**

- **Framework:** Filament resources, pages, and widgets
- **Styling:** Tailwind CSS utility classes
- **Icons:** Heroicons (`use Filament\Support\Icons\Heroicon;`)
- **Location:** `app/Filament/` directory

**Key patterns:**

```php
// Generating resources with correct model namespace
php artisan make:filament-resource User --generate --model-namespace=App\\Domains\\User\\Models

// Relation managers must specify full model path
php artisan make:filament-relation-manager --related-model=App\\Domains\\User\\Models\\User --attach Role users username
```

**Authorization:** Panel access is controlled via `User::canAccessPanel()` method checking permissions against panel IDs defined as constants (e.g., `AdministrationPanelProvider::ID`).

---

## Template structure & best practices

- **Keep Blade declarative:** Push business logic into view models, presenters, or Livewire components. Use `@php` blocks sparingly and never for business rules.
- **Component reusability:** Prefer Blade components (`<x-component>`) or includes for repeated UI fragments. Register view composers for globally shared data (navigation, user context).
- **Layout hierarchy:** Respect Laravel's layout stack by extending base layouts, defining `@section` blocks explicitly, and using `@push('scripts')`/`@stack` for per-page assets.
- **Security helpers:** Always use built-in helpers (`@can`, `@csrf`, `@vite`, `@method`) instead of manual HTML to maintain consistency and security.
- **Avoid inline PHP:** Never embed business logic in views. Views should only handle presentation logic (loops, conditionals for display).

---

## Styling & assets

- **Formatting:** Run `pnpm format` to lint CSS/SCSS via Prettier before committing.
- **Northwestern UI:** Use Bootstrap 5 utility classes for user-facing interfaces. Favor utility classes over custom CSS when possible.
- **Filament:** Use Tailwind CSS utilities within Filament resources. Do not mix Bootstrap classes in Filament views.

---

## Livewire & interactivity

- **Component responsibility:** Livewire components focus on state management; delegate heavy processing to backend action classes.
- **Data exposure:** Only expose data needed by the view. Avoid passing entire models - use DTOs or select specific properties.
- **Validation:** Use `rules()` method or FormRequest classes for validation, never inline validation logic.
- **Actions integration:** Call action classes from Livewire methods instead of embedding business logic:

    ```php
    public function submit()
    {
        $this->validate();

        app(CreateUserAction::class)(
            name: $this->name,
            email: $this->email
        );

        $this->reset();
    }
    ```

---

## Filament customization

- **Form builders:** Use Filament's form builder methods. Keep form definitions in dedicated methods for reusability.
- **Table configuration:** Define filters, actions, and bulk actions in the resource's `table()` method.
- **Navigation:** Use navigation groups and sort orders to organize panel structure. Define navigation via resource's `$navigationGroup` and `$navigationSort` properties.

---

## Accessibility & performance

- **WCAG 2.1 AA compliance:** All views must meet accessibility standards:
    - Semantic heading hierarchy (`<h1>` → `<h2>` → `<h3>`)
    - All form controls must have associated `<label>` elements
    - Visible focus states on interactive elements
    - ARIA attributes only when semantic HTML is insufficient
    - Sufficient color contrast ratios (4.5:1 for normal text, 3:1 for large text)
- **Images:** Always include descriptive `alt` text. Use empty `alt=""` only for decorative images. Optimize images before committing.
- **Icons:** Mark decorative icons with `aria-hidden="true"`. Provide text alternatives for functional icons.
- **Asset optimization:**
    - Defer non-critical JavaScript using `@vite` with proper chunking
    - Avoid inlining large unminified bundles
    - Use lazy loading for images below the fold
    - Leverage browser caching via versioned assets
- **Progressive enhancement:** Render critical content server-side, then layer interactive behaviors (dropdowns, modals, tabs) through Livewire, Alpine, or Bootstrap data attributes.
