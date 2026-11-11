{{--
    Exception details show to everyone outside production, where testers often use
    accounts without administrative permissions or Sentry, so they can say what
    failed when they report it. In production only Manage All can see them.

    The database may be why this page renders, so anything that
    could query it is wrapped in rescue() and fails closed.
--}}
@php
    use App\Domains\Access\Enums\SystemPermission;

    $isProduction = app()->environment('production');
    $user = rescue(fn() => auth()->user(), null, report: false);
    $userCanViewDetails = (bool) rescue(fn() => $user?->can(SystemPermission::ManageAll), false, report: false);
    $showDetails = !$isProduction || $userCanViewDetails;
    $sentryEventId = app()->bound('sentry') ? app('sentry')->getLastEventId() : null;
@endphp

<x-error-layout title="Something Went Wrong"
                :code="500"
                :navigation="false">
    Wait a moment and try again.

    <x-slot:help>
        <p class="mt-2 text-gray-600">
            If the problem continues, contact the
            <a class="text-nu-purple-100 font-semibold underline"
               href="https://www.it.northwestern.edu/support/service-desk/"
               target="_blank"
               rel="noopener noreferrer">IT Service Desk</a>.
        </p>

        @if ($sentryEventId)
            <p class="mt-4 font-mono text-xs uppercase tracking-wide text-gray-500">Error ID: {{ $sentryEventId }}</p>
        @endif
    </x-slot:help>

    <x-slot:details>
        @if ($sentryEventId)
            <div class="mt-12 border border-gray-200 bg-gray-50 p-6">
                <h2 class="font-nu-heading text-lg font-bold text-gray-900">Help Us Fix This</h2>
                <p class="mt-1 text-sm text-gray-600">If you'd like to help, tell us what happened.</p>

                <form class="mt-6 space-y-4" id="error-report-form">
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
                                  placeholder="What were you doing when the error happened?"></textarea>
                    </label>
                    <div class="flex items-center justify-end gap-4">
                        <p class="text-sm text-gray-600"
                           id="error-report-status"
                           role="status"></p>
                        <button class="bg-nu-purple-100 hover:bg-nu-purple-120 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                type="submit">
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showDetails && isset($exception))
            <div class="mt-12">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-nu-heading text-lg font-bold text-gray-900">Technical Details</h2>
                    <span
                          class="{{ $isProduction ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }} px-2 py-1 text-xs font-semibold uppercase">
                        {{ $isProduction ? 'Administrators Only' : 'Non-Production Only' }}
                    </span>
                </div>

                {{-- abort(500) and some exceptions carry no message: no empty box for them. --}}
                @if (filled($exception->getMessage()))
                    <p
                       class="mt-3 break-words border-l-4 border-red-600 bg-red-50 p-4 font-mono text-sm font-semibold text-red-800">
                        {{ $exception->getMessage() }}
                    </p>
                @endif

                <details class="mt-3">
                    <summary
                             class="pointer-coarse:py-3 cursor-pointer bg-gray-900 px-4 py-2 text-sm font-semibold text-white">
                        View Full
                        Stack Trace</summary>
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap bg-gray-900 p-4 font-mono text-xs text-gray-100">{{ $exception->getTraceAsString() }}</pre>
                </details>
            </div>
        @endif
    </x-slot:details>

    @if ($sentryEventId)
        @push('scripts')
            <script>
                document.getElementById('error-report-form')?.addEventListener('submit', (event) => {
                    event.preventDefault();

                    const form = event.currentTarget;
                    const button = form.querySelector('button[type="submit"]');
                    const status = document.getElementById('error-report-status');

                    if (!window.Sentry?.captureFeedback) {
                        status.textContent = 'Reporting isn\'t available right now.';

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
                    status.textContent = 'Thanks. Your report was sent.';
                });
            </script>
        @endpush
    @endif
</x-error-layout>
