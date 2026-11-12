<?php

declare(strict_types=1);

use App\Domains\Api\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Api\Enums\ClientOrigin;

/*
 * The OAuth consent screens a connected application or MCP client opens pass axe.
 */

beforeEach(fn () => $this->signInAsNorthwesternUser());

it('is healthy on the OAuth consent screen', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)(
        'Reporting Tool',
        ['http://localhost:4100/callback'],
        false,
        ['view-users'],
        description: 'Weekly enrollment reports.',
    );

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'scope' => 'view-users',
        'state' => 'browser-test',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertSee('Connect Reporting Tool')->assertSee('Approving returns you to'))->toBeHealthyInEachTheme();
});

it('is healthy on the OAuth consent screen for an internationalized domain', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['https://bücher.example/callback'], false, ['view-users']);

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://bücher.example/callback',
        'response_type' => 'code',
        'scope' => 'view-users',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertVisible('@internationalized-domain-warning'))->toBeHealthyInEachTheme();
});

it('is healthy on the page for an application that is no longer registered', function () {
    [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['http://localhost:4100/callback'], false, ['view-users']);
    $client->delete();

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertSee('Application Not Registered'))->toBeHealthyInEachTheme();
});

it('is healthy on the consent screen for a self-registered MCP client', function () {
    // Connecting an MCP client takes a permission a person without roles lacks.
    $this->signInAsSuperAdministrator();
    $client = $this->postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://localhost:4100/callback'],
    ])->assertCreated()->json('client_id');

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client,
        'redirect_uri' => 'http://localhost:4100/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'browser-test',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertVisible('@unverified-client')->assertSee('Name not verified'))->toBeHealthyInEachTheme();
});

it('is healthy on the consent screen for an MCP client from a client metadata document', function () {
    $this->signInAsSuperAdministrator();
    // Visit it by UUID: the browser's server can't see this test's fake for claude.ai.
    [, $client] = resolve(RegisterOAuthApplication::class)(
        'Claude Code',
        ['http://localhost/callback', 'http://127.0.0.1/callback'],
        false,
        ['mcp:use'],
        origin: ClientOrigin::Dynamic,
        clientMetadataUrl: 'https://claude.ai/oauth/claude-code-client-metadata',
    );

    $page = visit('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://127.0.0.1:4100/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'browser-test',
        'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
        'code_challenge_method' => 'S256',
    ]));

    expect($page->assertVisible('@verified-client')->assertSee('Verified by claude.ai'))->toBeHealthyInEachTheme();
});
