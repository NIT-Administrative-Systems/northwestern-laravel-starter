{{--
    Rendered for UnknownOAuthClientException: a person reached the
    OAuth consent screen from a client since deleted or revoked.

    An MCP client keeps the client ID it registered, so the person
    must remove the server from the client, then add it again.
--}}
<x-error-layout title="Application Not Registered" :code="400">
    The application that sent you here is no longer registered with {{ config('app.name') }}. If you were
    connecting an AI client, remove {{ config('app.name') }} from it and add it again.
</x-error-layout>
