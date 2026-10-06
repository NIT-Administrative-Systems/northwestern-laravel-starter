<x-mail::message>
# Your personal access token is expiring

Hello {{ $user->first_name ?: $user->full_name }},

Your personal access token **{{ $token->name }}** for **{{ config('app.name') }}** expires at {{ $expiresAt }}, in {{ $expiresIn }}. Anything that uses it stops working then.

To keep it working, create a new token and switch to it before this one expires.

<x-mail::button :url="$accessTokensUrl">
Manage Personal Access Tokens
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
You can turn these emails off on your [Preferences]({{ $preferencesUrl }}) page.
</x-slot:subcopy>
</x-mail::message>
