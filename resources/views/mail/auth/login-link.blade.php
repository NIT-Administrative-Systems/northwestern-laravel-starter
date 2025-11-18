<x-mail::message>
Hello {{ $user->first_name }},

A request was made to sign in to your **{{ config('app.name') }}** account using a secure one-time link.

To proceed, please use the button below:

<x-mail::button :url="$url">
    Sign In
</x-mail::button>

## About this sign-in link
- It expires in **{{ $expirationMinutes }} minutes**.
- It can be used **only once**.
- Do not forward or share this link with anyone.

If you did not request this sign-in link, you may safely ignore this message. No changes will be made to your account.

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
If you're unable to click the button, copy and paste the following URL into your web browser:

{{ $url }}
</x-slot:subcopy>
</x-mail::message>
