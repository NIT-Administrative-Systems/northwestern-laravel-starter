<x-filament-widgets::widget>
    <div
         class="rounded-xl border border-blue-200/80 bg-blue-50/80 p-4 text-sm text-blue-900 shadow-sm dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-100">
        <div class="flex items-start gap-3">
            <x-filament::icon class="mt-0.5 h-6 w-6 text-blue-500 dark:text-blue-300"
                              icon="heroicon-o-information-circle" />

            <div class="space-y-2">
                <p class="font-semibold tracking-tight">
                    Read-only submission log
                </p>

                <p class="leading-relaxed">
                    This page provides a record of support tickets submitted through the contact form. It is not a
                    ticket management system. Tickets are managed in the configured external system (e.g., TeamDynamix)
                    or through the support team's email inbox.
                </p>

                <p class="leading-relaxed text-blue-700 dark:text-blue-300/90">
                    Use this log to verify whether submissions were delivered successfully, identify any delivery
                    failures, and confirm that fallback emails were sent when a primary gateway error occurred.
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
