{{--
    The OAuth consent screen. Passport renders it (see OAuthServiceProvider) when an external
    application asks to act for the signed-in person, and posts the answer to its own approve
    and deny endpoints, which return to the application's registered redirect URI.

    @var \App\Domains\Auth\Models\OAuthClient $client
    @var \App\Domains\User\Models\User $user
    @var list<\Laravel\Passport\Scope> $scopes
    @var \Illuminate\Http\Request $request
    @var string $authToken
--}}
@php
    $redirectUri = $request->string('redirect_uri')->toString() ?: $client->redirect_uris[0] ?? '';
    $redirectHost = parse_url($redirectUri, PHP_URL_HOST) ?: $redirectUri;
    $unverified = $client->isMcpClient();
    // Passport narrows a token to the scopes its client may have only when it issues it, so the
    // screen lists only those, never more than the application can actually get.
    $scopes = array_values(array_filter($scopes, fn($scope) => $client->hasScope($scope->id)));
@endphp

<x-layouts.public title="Connect {{ $client->name }}">
    <section class="mx-auto max-w-xl px-4 py-12 sm:px-6 lg:py-16">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 sm:p-8">
            <h1 class="font-nu-heading text-nu-purple-100 text-2xl font-bold tracking-tight">
                {{ $client->name }} Wants to Access Your {{ config('app.name') }} Account
            </h1>

            @if ($unverified)
                <p class="mt-3">
                    <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
                        Unverified AI Client
                    </x-filament::badge>
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    This AI client registered itself, so its name hasn't been verified. Only approve it if you started
                    this connection.
                </p>
            @elseif (filled($client->description))
                <p class="mt-3 text-gray-600">{{ $client->description }}</p>
            @endif

            <h2 class="mt-6 text-base font-semibold text-gray-950">What It Can Do as You</h2>
            <ul class="mt-2 list-disc space-y-1 ps-5 text-gray-700">
                {{-- A self-registered MCP client's token works only on the MCP server, never the REST API's /v1/me. --}}
                @unless ($unverified)
                    <li>See your account details</li>
                @endunless
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm text-gray-600">
                It can never do more than your own permissions allow. You can disconnect it at any time on your Account
                page.
            </p>

            <p class="mt-6 text-sm text-gray-600">
                You'll return to <strong class="text-gray-950">{{ $redirectHost }}</strong>.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <form method="post" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input name="state"
                           type="hidden"
                           value="{{ $request->state }}">
                    <input name="client_id"
                           type="hidden"
                           value="{{ $client->getKey() }}">
                    <input name="auth_token"
                           type="hidden"
                           value="{{ $authToken }}">
                    <x-filament::button type="submit" icon="heroicon-m-check">Approve</x-filament::button>
                </form>

                <form method="post" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input name="state"
                           type="hidden"
                           value="{{ $request->state }}">
                    <input name="client_id"
                           type="hidden"
                           value="{{ $client->getKey() }}">
                    <input name="auth_token"
                           type="hidden"
                           value="{{ $authToken }}">
                    <x-filament::button type="submit"
                                        color="gray"
                                        outlined>Deny</x-filament::button>
                </form>
            </div>

            <div class="mt-8 border-t border-gray-100 pt-4 text-sm text-gray-600">
                Signed in as <strong class="text-gray-950">{{ $user->full_name }}</strong>
                @if (filled($user->email))
                    ({{ $user->email }})
                @endif
                <form class="inline"
                      method="post"
                      action="{{ route('oauth.switch-account') }}">
                    @csrf
                    <input name="return_to"
                           type="hidden"
                           value="{{ $request->fullUrl() }}">
                    Not you? <button class="text-nu-purple-100 underline" type="submit">Switch Account</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.public>
