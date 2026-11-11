<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Changelog
    |--------------------------------------------------------------------------
    |
    | Turns on the changelog page and its navigation item. Entries
    | can be seeded whether or not the changelog is turned on.
    |
    */

    'enabled' => env('CHANGELOG_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Jira Integration
    |--------------------------------------------------------------------------
    |
    | A Jira issue key wrapped in backticks, such as `GSTS-1234`,
    | becomes a link to the issue when both of these are set.
    | Only keys that start with the identifier are linked.
    |
    */

    'jira' => [
        'url' => env('JIRA_URL', 'https://nuitadminsystems.atlassian.net'),
        'identifier' => env('JIRA_ISSUE_IDENTIFIER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | How many entries each page of the changelog shows.
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
    | How many entries the feed at `/support/changelog/feed.rss` includes.
    |
    */

    'feed' => [
        'limit' => 20,
    ],

];
