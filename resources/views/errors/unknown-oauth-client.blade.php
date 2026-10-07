{{--
    Rendered for UnknownOAuthClientException: a person reached the OAuth consent screen from a
    client that was deleted or revoked. An MCP client keeps the client ID it registered, so the
    person has to remove the server from it and add it again.
--}}
<x-error-layout title="Application Not Registered">
    The application that sent you here is no longer registered with {{ config('app.name') }}. If you were
    connecting an AI client, remove {{ config('app.name') }} from it and add it again.
</x-error-layout>
