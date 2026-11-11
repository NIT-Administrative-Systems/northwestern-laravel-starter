# Branding & Mail

The [Northwestern Filament Theme](https://github.com/NIT-Administrative-Systems/northwestern-filament-theme) (`northwestern-sysdev/northwestern-filament-theme`) gives every page the look of the university’s Department Templates 4.0. Both panel providers register it as a plugin, `NorthwesternTheme::make()`, with `->withoutAssetRegistration()` because each panel’s Vite theme imports the theme’s CSS itself.

## Colors and Fonts

Colors and fonts come from [`@nu-appdev/northwestern-tokens`](https://github.com/NIT-Administrative-Systems/northwestern-tokens), which the theme package bundles. You don’t install the npm package. Akkurat Pro, Poppins and Noto Serif load from the university’s CDN.

The theme sets the panels’ Filament colors (`primary` is Northwestern Purple) and adds Tailwind utilities for the tokens, such as `font-nu-heading`, `text-nu-purple-100` and `bg-nu-purple-120`. Use them on [public pages](https://laravel-starter.entapp.northwestern.edu/building/public-pages/) and [error pages](https://laravel-starter.entapp.northwestern.edu/building/error-pages/) in place of literal colors.

## The Wordmark and a Unit Lockup

The panels’ top bar and `<x-site-header>`, on public, sign-in and error pages, show the Northwestern wordmark. To show your unit’s lockup instead, set `NU_LOCKUP` to a full URL or a path in `public/`; use the white version, since both headers are purple. The header renders it with the theme’s `<x-northwestern-filament-theme::lockup />` component, at 1.5rem with the brand’s clear space around it, linking to northwestern.edu, and its accessible name is “Northwestern University” followed by `NU_UNIT_NAME`. The component reads only config, so error pages show the lockup too. The footer always shows the “Northwestern University” wordmark.

## The Impersonation Banner

While an administrator is impersonating someone, the panels show the theme’s impersonation banner above their top bar, with **Leave Impersonation**. `<x-site-header>` renders the same banner above itself with `<x-northwestern-filament-theme::impersonation-banner />`, so public, sign-in and error pages show it too. Its defaults match the panels’ `->impersonationBanner()`: it reads lab404’s impersonation session, names the signed-in user, and posts to the `impersonate.leave` route. It renders nothing when no one is impersonating, and on an error page where the session or the signed-in user can’t be read it is left out rather than failing the page.

## Light Pages Before Signing In

Every page a visitor sees before signing in is light: the public and error layouts, and the app panel’s simple pages (sign-in, the email code step and the lockdown page), whatever the browser’s theme. Department Templates 4.0 has no dark mode. Dark mode starts inside the panels, which follow each person’s theme setting. The app panel keeps its simple pages light with a short script, `resources/views/filament/app/light-only.blade.php`, that it renders on every page using `HasSiteHeader`; see [Simple Pages](https://laravel-starter.entapp.northwestern.edu/building/app-panel/#simple-pages).

The purple header and the footer share one keyboard focus ring, a 3px white outline set 2px off the control. The app and administration themes and `resources/css/errors.css` set it on `<x-site-header>` and the panels’ top bar, matching the theme’s footer.

## The Footer

The university’s Web Style Guide requires every page to show the responsible unit’s contact details and a set of university links. The theme’s footer renders both:

* **Your unit’s details**: name, address, city, phone, fax and email.
* **Quick Links**, your own links, when you set any.
* **Connect**, the social accounts, unless that list is empty.
* **Northwestern Resources** and the bottom bar: Building Access, Campus Emergency Information, Careers, Contact Northwestern University, University Policies, Accessibility, Disclaimer, Privacy Statement and Report a Concern. These are required and can’t be removed.

It appears on every page end users see: the app panel’s pages (below the card on sign-in and the lockdown page), public pages through `<x-layouts.public>`, and the 500, 503 and database-paused pages through `<x-layouts.error>`. The administration panel turns it off with `->footer(false)`, as a back-office tool. A layout of your own renders it with `<x-northwestern-filament-theme::footer />`, which needs no Filament, auth or database.

Its content is set in `config/northwestern-filament-theme.php`:

config/northwestern-filament-theme.php

```php
'lockup' => env('NU_LOCKUP'),          // null shows the Northwestern wordmark
'unit' => [
    'name' => env('NU_UNIT_NAME'),
    'address' => env('NU_UNIT_ADDRESS'),
    'city' => env('NU_UNIT_CITY'),
    'phone' => env('NU_UNIT_PHONE'),
    'fax' => env('NU_UNIT_FAX'),
    'email' => env('NU_UNIT_EMAIL'),
],
'footer' => [
    'links' => [],                     // Quick Links, as label => URL
    'social' => [/* network => URL */],
],
```

A `null` unit field falls back to Information Technology’s details, and an empty string hides that field. `.env.example` lists every `NU_UNIT_*` setting and `NU_LOCKUP` as `null`; replace them with your unit’s values when you set up the project; see [Your Unit’s Details](https://laravel-starter.entapp.northwestern.edu/getting-started/initial-customization/#6-your-units-details). The config file lists the social networks the footer has icons for.

## Email

Email uses Laravel’s Markdown mail with the starter’s theme. The published mail components are in `resources/views/vendor/mail/` (`html/` and `text/`), and `html/themes/default.css` colors the background, buttons, links and panels Northwestern Purple.

Every email built on `<x-mail::message>` gets:

* A header with the application name, linking to `APP_URL`.
* The footer from `resources/views/mail/partials/footer.blade.php`: your unit’s details, read from the same config as the page footer, the Accessibility and Privacy Statement links, and the copyright. The HTML part renders it as Markdown and the text part as plain text.

The footer is required on university email, so keep the `@include('mail.partials.footer')` in both `message.blade.php` files.

### Writing an Email

1. **Create the Mailable** in its domain, for example `app/Domains/Report/Mail/ReportReadyMail.php`, and point `content()` at a Markdown view:

   ```php
   public function content(): Content
   {
       return new Content(markdown: 'mail.reports.ready', with: ['report' => $this->report]);
   }
   ```

2. **Write the view** in `resources/views/mail/`, wrapped in `<x-mail::message>`. Keep the Markdown flush left:

   ```blade
   <x-mail::message>
   # Your report is ready


   **{{ $report->title }}** finished processing.


   <x-mail::button :url="$report->url">
   View report
   </x-mail::button>


   <x-slot:subcopy>
   You received this because you requested the report.
   </x-slot:subcopy>
   </x-mail::message>
   ```

3. **Send it** with `Mail::to($user)->send(new ReportReadyMail($report))`, or queue it. Locally, mail goes to the mail catcher set by `MAIL_HOST` and `MAIL_PORT`. When `MAIL_CAPTURE_URL` points at a catcher’s web interface, such as MailPit, the administration panel links to it under Developer Tools.

`resources/views/mail/auth/login-code.blade.php` and `resources/views/mail/personal-access-token-expiration.blade.php` are working examples.

> **Don't let a formatter indent mail views**
>
> Markdown treats a line indented by four spaces as a code block, so an indented paragraph in a mail view arrives as a gray block of code. `.prettierignore` excludes `resources/views/mail/` and `resources/views/vendor/mail/` for this reason. Keep them out of any other formatter you add.
