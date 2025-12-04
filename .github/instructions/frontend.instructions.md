---
applyTo: "resources/views/**/*.blade.php,resources/views/**/*.php,resources/css/**/*.{css,scss},resources/sass/**/*.scss,resources/**/*.{astro,html}"
---

# Frontend & UI Instructions

## Template structure

- Keep Blade files declarative: push logic into view models, presenters, or Livewire components. Use `@php` blocks sparingly and never for business rules.
- Prefer Blade components (`<x-app::button>`) or includes for repeated UI fragments. Register view composers when data is shared globally (e.g., navigation, user context).
- Respect Laravel's layout stack: extend base layouts, define `@section` blocks explicitly, and use `@push('scripts')`/`@stack` to load per-page assets.
- Use built-in helpers (`@can`, `@csrf`, `@vite`) instead of manual HTML wiring to keep security features consistent.

## Styling & assets

- Run `pnpm format` to keep CSS/SCSS linted via Prettier; favor CSS custom properties or Tailwind utilities over ad-hoc inline styles.

## Livewire, Filament & interactivity

- Livewire components should focus on state management; heavy processing belongs in backend action classes. Expose only the data needed by the view and validate using `rules`/form objects.
- For Filament resources, use provided form/table builders. When customizing views, keep overrides in `resources/views/vendor/filament` and document deviations from upstream defaults.
- Enhance interactions progressively: render critical content server-side, then layer JS behaviors (dropdowns, tabs) through Livewire, Alpine, or Bootstrap data attributes.

## Accessibility & performance

- All views must satisfy WCAG 2.1 AA: semantic headings, labeled form controls, visible focus states, ARIA attributes only when necessary.
- Keep images optimized and include `alt` text. Defer large script blocks using `@vite(['resources/js/...'])` and avoid inlining unminified bundles.
