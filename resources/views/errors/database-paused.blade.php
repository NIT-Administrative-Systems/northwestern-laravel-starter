<x-layouts.error title="Database Paused">
    <section class="mx-auto max-w-2xl px-4 py-20 text-center sm:px-6 lg:py-28">
        <h1 class="font-nu-heading text-nu-purple-100 text-4xl font-bold tracking-tight">Database Paused</h1>

        <p class="mt-6 text-lg text-gray-600">
            Our <span class="bg-gray-100 px-1.5 py-0.5 font-mono text-sm font-semibold text-gray-700">{{ strtoupper(config('app.env')) }}</span>
            environment uses an auto-scaling database that pauses during periods of inactivity to optimize resource usage and costs.
        </p>

        <div class="mt-8 flex items-center justify-center gap-3 text-gray-600"
             role="status">
            <span class="border-nu-purple-100 size-5 animate-spin rounded-full border-2 border-t-transparent"
                  aria-hidden="true"></span>
            Database is starting up...
        </div>

        <p class="mt-6 text-sm text-gray-500">
            This page will automatically refresh in
            <span class="text-nu-purple-100 font-semibold"
                  id="countdown">30</span>
            seconds.
        </p>
    </section>

    @push('scripts')
        <script>
            let countdown = 30;
            const countdownElement = document.getElementById('countdown');

            const timer = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        </script>
    @endpush
</x-layouts.error>
