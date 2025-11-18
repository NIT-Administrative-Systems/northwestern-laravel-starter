<x-filament-widgets::widget>
    <div
         class="border-warning-200/80 bg-warning-50/80 text-warning-900 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-100 rounded-xl border p-4 text-sm shadow-sm">
        <div class="flex items-start gap-3">
            <x-filament::icon class="text-warning-500 dark:text-warning-300 mt-0.5 h-6 w-6"
                              icon="heroicon-o-shield-exclamation" />

            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold tracking-tight">
                        No protected API routes detected
                    </p>
                </div>

                <p class="leading-relaxed">
                    This dashboard records traffic for routes that are protected by the
                    <code
                          class="bg-warning-100/60 dark:bg-warning-500/20 rounded px-1.5 py-0.5 font-mono text-[0.8rem]">AuthenticatesApiTokens</code>
                    middleware. No matching routes have been observed in
                    <code
                          class="bg-warning-100/60 dark:bg-warning-500/20 rounded px-1.5 py-0.5 font-mono text-[0.8rem]">routes/api.php</code>.
                </p>

                <div class="space-y-1">
                    <p class="text-warning-700 dark:text-warning-300 text-xs font-semibold uppercase tracking-wide">
                        Next steps
                    </p>

                    <ul class="text-warning-900/90 dark:text-warning-100/90 list-disc space-y-1 pl-5">
                        <li>
                            Protect at least one route with
                            <code
                                  class="bg-warning-100/60 dark:bg-warning-500/20 rounded px-1.5 py-0.5 font-mono text-[0.8rem]">AuthenticatesApiTokens</code>
                            to begin collecting metrics.
                        </li>
                        <li>
                            If your application does not provide a programmatic API, disable API access to hide this
                            dashboard by setting
                            <code
                                  class="bg-warning-100/60 dark:bg-warning-500/20 rounded px-1.5 py-0.5 font-mono text-[0.8rem]">API_ENABLED=false</code>
                            in your environment variables.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
