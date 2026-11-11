{{--
    Rendered for AccountDeactivated: a deactivated account tried to sign in. Only an administrator's
    Reactivate brings it back.
--}}
<x-error-layout title="Account Deactivated">
    Your {{ config('app.name') }} account is deactivated, so you can't sign in. Contact the team that supports
    {{ config('app.name') }} to have it reactivated.
</x-error-layout>
