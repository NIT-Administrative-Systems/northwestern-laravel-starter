@php
    use App\Domains\Auth\Enums\SystemPermission;

    /**
     * Exception Detail Visibility Logic
     *
     * In non-production environments, we unconditionally display exception details to streamline the debugging process.
     * Analysts and developers often test using standard user accounts that lack administrative permissions. Since some
     * of these users may not have access to backend monitoring tools like Sentry, exposing the exception details
     * directly in the UI helps them infer the nature of the failure and facilitates more accurate issue reports.
     *
     * In production, visibility is strictly limited to users with the ManageAll permission (typically administrators).
     * This safeguards sensitive system information from being exposed to the general user base, while still granting
     * administrators immediate access to stack traces. This allows for rapid diagnosis of live issues without having
     * to cross-reference external logs or Sentry data.
     *
     * This page can render because the database is unavailable, so anything that may query it is wrapped in
     * rescue() and fails closed.
     */
    $isProduction = app()->environment('production');
    $user = rescue(fn () => auth()->user(), null, report: false);
    $userCanViewDetails = (bool) rescue(fn () => $user?->can(SystemPermission::ManageAll), false, report: false);
    $showDetails = ! $isProduction || $userCanViewDetails;
    $sentryEventId = app()->bound('sentry') ? app('sentry')->getLastEventId() : null;
@endphp

<x-layouts.error title="Something went wrong">
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:py-20">
        <div class="text-center">
            <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold tracking-tight">Something went wrong</h1>

            <p class="mt-6 text-lg text-gray-600">Please wait a moment and try again.</p>

            <p class="mt-2 text-gray-600">
                If the problem persists, please contact the
                <a class="text-nu-purple-100 font-semibold underline"
                   href="https://www.it.northwestern.edu/support/service-desk/"
                   target="_blank"
                   rel="noopener noreferrer">IT Service Desk</a>
                for assistance.
            </p>

            @if ($sentryEventId)
                <p class="mt-4 font-mono text-xs uppercase tracking-wide text-gray-500">Error ID: {{ $sentryEventId }}</p>
            @endif
        </div>

        @if ($sentryEventId)
            <div class="mt-12 border border-gray-200 bg-gray-50 p-6">
                <h2 class="font-nu-heading text-lg font-bold text-gray-900">Help us fix this</h2>
                <p class="mt-1 text-sm text-gray-600">If you'd like to help, tell us what happened.</p>

                <form class="mt-6 space-y-4"
                      id="error-report-form">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            Name
                            <input class="focus:border-nu-purple-100 focus:ring-nu-purple-100 mt-1 block w-full border border-gray-300 bg-white px-3 py-2 font-normal"
                                   name="name"
                                   type="text"
                                   value="{{ $user->full_name ?? '' }}"
                                   autocomplete="name">
                        </label>
                        <label class="block text-sm font-semibold text-gray-700">
                            Email
                            <input class="focus:border-nu-purple-100 focus:ring-nu-purple-100 mt-1 block w-full border border-gray-300 bg-white px-3 py-2 font-normal"
                                   name="email"
                                   type="email"
                                   value="{{ $user->email ?? '' }}"
                                   autocomplete="email">
                        </label>
                    </div>
                    <label class="block text-sm font-semibold text-gray-700">
                        What happened?
                        <textarea class="focus:border-nu-purple-100 focus:ring-nu-purple-100 mt-1 block w-full resize-none border border-gray-300 bg-white px-3 py-2 font-normal"
                                  name="message"
                                  rows="4"
                                  placeholder="Describe what you were doing when the error occurred..."></textarea>
                    </label>
                    <div class="flex items-center justify-end gap-4">
                        <p class="text-sm text-gray-600"
                           id="error-report-status"
                           role="status"></p>
                        <button class="bg-nu-purple-100 hover:bg-nu-purple-120 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                type="submit">
                            Submit report
                        </button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showDetails && isset($exception))
            <div class="mt-12">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-nu-heading text-lg font-bold text-gray-900">Technical details</h2>
                    <span class="{{ $isProduction ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }} px-2 py-1 text-xs font-semibold uppercase">
                        {{ $isProduction ? 'Administrators only' : 'Non-production only' }}
                    </span>
                </div>

                <p class="mt-3 break-words border-l-4 border-red-600 bg-red-50 p-4 font-mono text-sm font-semibold text-red-800">
                    {{ $exception->getMessage() }}
                </p>

                <details class="mt-3">
                    <summary class="cursor-pointer bg-gray-900 px-4 py-2 text-sm font-semibold text-white">View full stack trace</summary>
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap bg-gray-900 p-4 font-mono text-xs text-gray-100">{{ $exception->getTraceAsString() }}</pre>
                </details>
            </div>
        @endif
    </section>

    @if ($sentryEventId)
        @push('scripts')
            <script>
                document.getElementById('error-report-form')?.addEventListener('submit', (event) => {
                    event.preventDefault();

                    const form = event.currentTarget;
                    const button = form.querySelector('button[type="submit"]');
                    const status = document.getElementById('error-report-status');

                    if (!window.Sentry?.captureFeedback) {
                        status.textContent = 'Error reporting is unavailable.';

                        return;
                    }

                    window.Sentry.captureFeedback({
                        associatedEventId: @js($sentryEventId),
                        name: form.elements.name.value,
                        email: form.elements.email.value,
                        message: form.elements.message.value,
                    });

                    form.reset();
                    button.disabled = true;
                    status.textContent = 'Thank you. Your report has been sent.';
                });
            </script>
        @endpush
    @endif
</x-layouts.error>
