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
    $unverified = $client->isMcpClient();
    // Passport narrows a token to the scopes its client may have only when it issues it, so the
    // screen lists only those, never more than the application can actually get.
    $scopes = array_values(array_filter($scopes, fn($scope) => $client->hasScope($scope->id)));
@endphp

<x-layouts.public title="Connect {{ $client->name }}">
    <section class="mx-auto max-w-lg px-4 py-12 sm:px-6 lg:py-16">
        <div class="bg-nu-purple-100 h-1 rounded-t-xl"></div>

        <div class="rounded-b-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 sm:p-8">
            <h1 class="font-nu-heading text-nu-purple-100 text-2xl font-bold tracking-tight">
                Connect {{ $client->name }}
            </h1>

            <p class="mt-2 text-gray-700">
                <strong class="font-semibold text-gray-950">{{ $client->name }}</strong> wants to access your
                <strong class="text-nu-purple-100 font-semibold">{{ config('app.name') }}</strong> account.
            </p>

            @if ($unverified)
                <x-filament::callout class="mt-5"
                                     color="warning"
                                     icon="heroicon-o-exclamation-triangle">
                    <x-slot name="description">
                        <strong>Unverified AI client.</strong> It registered itself, so its name hasn't been verified.
                        Only approve it if you started this connection.
                    </x-slot>
                </x-filament::callout>
            @elseif (filled($client->description))
                <p class="mt-3 text-sm text-gray-600">{{ $client->description }}</p>
            @endif

            <div class="mt-6 rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">What It Can Do as You</h2>

                <ul class="mt-3 space-y-2">
                    {{-- A self-registered MCP client's token works only on the MCP server, never the REST API's /v1/me. --}}
                    @unless ($unverified)
                        <li class="flex gap-2 text-sm text-gray-700">
                            <x-filament::icon class="text-nu-purple-100 size-5 shrink-0" icon="heroicon-m-check" />
                            See your account details
                        </li>
                    @endunless
                    @foreach ($scopes as $scope)
                        <li class="flex gap-2 text-sm text-gray-700">
                            <x-filament::icon class="text-nu-purple-100 size-5 shrink-0" icon="heroicon-m-check" />
                            {{ $scope->description }}
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 flex gap-2 border-t border-gray-200 pt-3 text-xs text-gray-600">
                    <x-filament::icon class="size-4 shrink-0 text-gray-500" icon="heroicon-m-shield-check" />
                    It can never do more than your own permissions allow. You can disconnect it at any time on your
                    Account page.
                </p>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
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
                    <x-filament::button class="w-full"
                                        type="submit"
                                        color="gray"
                                        outlined>Deny</x-filament::button>
                </form>

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
                    <x-filament::button class="w-full"
                                        type="submit"
                                        icon="heroicon-m-check">Approve</x-filament::button>
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
