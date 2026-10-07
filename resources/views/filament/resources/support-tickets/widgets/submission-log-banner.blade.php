<x-filament-widgets::widget>
    <div
         class="rounded-xl border border-blue-200/80 bg-blue-50/80 p-4 text-sm text-blue-900 shadow-sm dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-100">
        <div class="flex items-start gap-3">
            <x-filament::icon class="mt-0.5 h-6 w-6 text-blue-500 dark:text-blue-300"
                              icon="heroicon-o-information-circle" />

            <div class="space-y-2">
                <p class="font-semibold tracking-tight">
                    Read-Only Submission Log
                </p>

                <p class="leading-relaxed">
                    This page records requests sent through Contact Support. It isn't where tickets are managed: that's
                    the ticketing system (such as TeamDynamix) or the support team's mailbox.
                </p>

                <p class="leading-relaxed text-blue-700 dark:text-blue-300/90">
                    Use it to check that each request was delivered, find the ones that failed, and confirm that a
                    fallback email went out when the ticketing system didn't respond.
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
