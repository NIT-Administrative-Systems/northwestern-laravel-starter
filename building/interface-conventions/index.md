# Interface Conventions

The starter’s pages follow one set of rules for copy, and ship small helpers that apply them. Follow the same rules in your own pages so the application reads as one piece. The rules are also in `.github/copilot-instructions.md`, where coding agents read them.

## Interface Copy

Write copy the way Northwestern does: plainly, in the second person, in the active voice, without jargon on pages everyone uses. The [Northwestern A to Z Style Guide](https://www.northwestern.edu/brand/editorial-guidelines/style-guide/) and the [Northwestern IT Style Guide](https://www.it.northwestern.edu/departments/it-services-support/it-communications/branding/style-guide.html) settle anything not covered here.

### Names Are Title Case; Sentences Are Sentence Case

| Title case (names)                                                                                                                                                                                    | Sentence case (sentences)                                                                                                                                                                                   |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Page titles, navigation, tabs, section headings, wizard steps, buttons, menu items, modal headings, field labels that name a value, column headers, filters, enum labels, badges, notification titles | Descriptions, helper text, placeholders, modal descriptions, notification bodies, empty-state descriptions, validation and error messages, emails, toggle and checkbox labels, labels phrased as a question |

* A field label names a value (“Timezone”, “Allowed Scopes”). A toggle or checkbox states a choice the person turns on, so it’s a sentence (“Also notify the audience”), and so is a label that asks a question (“What happened?”).
* Title case follows Chicago headline style: articles, coordinating conjunctions and prepositions stay lowercase unless first or last (“Applications with Access to Your Account”). A verb’s particle and the second part of a hyphenated word are capitalized (“Sign In with Email”, “Sign-In Records”).
* Keep model labels lowercase (`protected static ?string $modelLabel = 'service client';`) unless they start with a proper noun or acronym (“MCP client”). Filament puts them into sentences as they are and title-cases them for titles and navigation.
* Filament’s built-in labels keep Filament’s own wording, which is in sentence case (“Save changes”, “New service client”, “No service clients”). The rule applies to the copy you write: your pages, resources, actions, fields and messages.

### One Name for One Thing

* **Sign in**, **sign-in** and **sign out**, never “log in”, “login” or “logout” in copy.
* **Personal access token** for the tokens people create; **service client** for an API user’s client; **application** for an OAuth application; **AI client** for an MCP client in copy everyone reads.
* **NetID**, **Northwestern Directory**, **Northwestern IT** and **IT Service Desk**, never “NU”, “NUIT” or other informal abbreviations.

### Northwestern Style

* Times: “4 p.m.”, “10:12 a.m.”, “noon” and “midnight”, the time before the date, months spelled out, and the year only when it isn’t this year.
* Spell out one through nine in sentences, and use real plurals, never “minute(s)”.
* Use the serial comma, no ampersands in place of “and”, and no exclamation points.
* Address the person as “you” and “your”, never “my”.

**Tone:** say what happened and what to do next, and leave out “Sorry”, “Please” and “Unable to”: “We couldn’t resend the code. Try again in a minute.” Keep every sentence true for any application built from the starter.

## Title Case

`Northwestern\SysDev\Chassis\Formatting\TitleCase::of()`, from [Chassis](https://laravel-starter.entapp.northwestern.edu/reference/chassis/), applies the title case rule. It keeps words that already carry capitals (“NetID”, “MCP”) and placeholders (`:label`) as they are.

```php
TitleCase::of('applications with access to your account'); // "Applications with Access to Your Account"
TitleCase::of('sign-in records');                          // "Sign-In Records"
```

## Dates and Counts in Sentences

`Northwestern\SysDev\Chassis\Formatting\NorthwesternDateTime` writes dates and times in Northwestern style, for emails, notifications and other sentences. Tables and compact displays keep Filament’s formats.

```php
NorthwesternDateTime::format($moment);          // "10:12 a.m. CDT Saturday, October 10"
NorthwesternDateTime::time($moment);            // "10:12 a.m.", "4 p.m.", "noon" or "midnight"
NorthwesternDateTime::date($moment);            // "Saturday, October 10", with ", 2027" when it isn't this year
```

Each takes an optional timezone; pass the person’s, `$user->timezone`, when you write to them.

`Northwestern\SysDev\Chassis\Formatting\CountInWords::of()` writes a count and its noun: “one minute”, “five seconds”, “30 seconds”.

## Date Range Filters

`Northwestern\FilamentTheme\Filters\DateRangeFilter`, from the Northwestern Filament theme, builds a table filter with **From** and **To** dates, its query and its indicators. It uses the browser’s date input, which works with screen readers.

```php
resolve(DateRangeFilter::class)->make(
    name: 'created_at_range',
    label: 'Date Range',
    column: 'created_at',
),
```

## Badges in Custom HTML

Filament’s badges render from columns and entries. When a column builds its own HTML, such as a list of roles in one cell, render Filament’s badge component with `Blade::render()`. It uses Filament’s own classes and colors, so it matches the badges Filament renders everywhere else, in light and dark mode:

```php
Blade::render(
    '<x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>',
    ['label' => 'Coordinators', 'color' => 'primary'],
);
```

`RoleActivityTable` and `RoleDefinitionHistoryTable` render their role lists this way.

> **No links inside clickable rows**
>
> When a table’s rows link to a record, every cell is already inside a link. Don’t put a link in a cell’s HTML: a link can’t contain another, and the browser splits them into empty links that fail accessibility checks.

## Headings in Rendered Markdown

Markdown written to stand on its own usually starts its headings at `###`, which skips a level under a page’s `<h1>`. `Northwestern\SysDev\Chassis\Markdown\ShiftHeadings` is a CommonMark extension that moves a document’s headings so the shallowest lands at the level you choose. The changelog uses it to start an entry’s headings at `<h2>` on its own page and `<h3>` on the index; see `Changelog::bodyHtml()`.
