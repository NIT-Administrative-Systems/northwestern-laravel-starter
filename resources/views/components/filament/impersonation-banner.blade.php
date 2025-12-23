@php
    if (!is_impersonating()) {
        return;
    }

    $user = auth()->user();
    $username = $user->full_name ?? $user->username;
    $leaveUrl = route('impersonate.leave');
@endphp

<style>
    :root {
        --impersonate-banner-height: 120px;
    }

    @media (min-width: 640px) {
        :root {
            --impersonate-banner-height: 56px;
        }
    }

    html {
        margin-top: var(--impersonate-banner-height);
    }

    #impersonate-banner-custom {
        position: fixed;
        top: 0;
        min-height: var(--impersonate-banner-height);
        width: 100%;
        z-index: 9999;
    }

    .fi-topbar {
        top: var(--impersonate-banner-height);
    }

    div.fi-layout>aside.fi-sidebar {
        top: var(--impersonate-banner-height);
        height: calc(100vh - var(--impersonate-banner-height));
    }

    @media print {
        #impersonate-banner-custom {
            display: none;
        }

        html {
            margin-top: 0;
        }
    }
</style>

<div class="border-b border-red-700 bg-red-600" id="impersonate-banner-custom">
    <div
         class="mx-auto flex h-full max-w-7xl flex-col items-center justify-center gap-2 px-4 py-3 sm:flex-row sm:justify-between sm:gap-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-center gap-3">
            <div class="flex-shrink-0">
                <x-filament::icon class="h-5 w-5 text-red-100"
                                  aria-hidden="true"
                                  :icon="\Filament\Support\Icons\Heroicon::ExclamationTriangle" />
            </div>
            <div
                 class="flex flex-wrap items-center justify-center gap-x-2 text-center text-sm font-medium text-white sm:text-left">
                <span>Impersonating user</span>
                <span class="hidden text-red-200 sm:inline">&bull;</span>
                <span class="font-semibold text-red-50">{{ $username }}</span>
            </div>
        </div>
        <div class="flex-shrink-0">
            <form method="POST" action="{{ $leaveUrl }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-md bg-white px-3.5 py-2 text-sm font-semibold text-red-600 shadow-sm transition-colors duration-150 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        type="submit">
                    <x-filament::icon class="h-4 w-4" :icon="\Filament\Support\Icons\Heroicon::ArrowRightEndOnRectangle" />
                    Leave Impersonation
                </button>
            </form>
        </div>
    </div>
</div>
