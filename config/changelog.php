<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Changelog
    |--------------------------------------------------------------------------
    |
    | A global toggle for the user-facing changelog feature. When disabled,
    | changelog routes will not be registered, and the navigation item
    | will be hidden. Changelog entries can still be seeded regardless
    | of this setting.
    |
    */

    'enabled' => env('CHANGELOG_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Jira Integration
    |--------------------------------------------------------------------------
    |
    | When both values are configured, Jira issue references wrapped in
    | backticks (e.g. `GSTS-1234`) will automatically be converted into
    | clickable links pointing to the Jira issue in the browser.
    |
    | Only references matching the configured identifier are linked;
    | other project prefixes are left as-is.
    |
    */

    'jira' => [
        // Base URL of your Jira instance
        'url' => env('JIRA_URL', 'https://nuitadminsystems.atlassian.net'),

        // Jira project key used to match issue references (e.g. "GSTS")
        'identifier' => env('JIRA_ISSUE_IDENTIFIER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Controls how many changelog entries are displayed per page on the
    | public changelog index.
    |
    */

    'pagination' => [
        'per_page' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | RSS Feed
    |--------------------------------------------------------------------------
    |
    | Settings for the RSS feed available at `/support/changelog/feed.rss`
    |
    */

    'feed' => [
        'limit' => 20,
    ],

];
