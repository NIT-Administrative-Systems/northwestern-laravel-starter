@php
    use App\Domains\User\Enums\PermissionEnum;
    use App\Domains\User\Enums\AuthTypeEnum;
    use App\Providers\Filament\AdministrationPanelProvider;
    use Filament\Facades\Filament;
@endphp

<ul class="navbar-nav">
    @unless (Route::is('login-*'))
        <li class="nav-item px-md-1">
            <a class="nav-link {{ Route::is('home') ? 'active' : '' }}" href="/">
                <i class="fas fa-home fa-fw me-1" aria-hidden="true"></i>
                Home
            </a>
        </li>
    @endunless

    @auth
        @can(PermissionEnum::ACCESS_ADMIN_PANEL)
            <li class="nav-item px-md-1">
                <a class="nav-link" href="{{ Filament::getPanel(AdministrationPanelProvider::ID)->getUrl() }}">
                    <i class="fas fa-gauge fa-fw me-1" aria-hidden="true"></i>
                    Dashboard
                </a>
            </li>
        @endcan
    @endauth
</ul>

<div class='mt-md-0 ms-auto mt-2'>
    <ul class="navbar-nav me-auto">
        @auth
            <li class="nav-item mb-auto mt-auto">
                <div class="d-flex align-items-center">
                    @if (config('platform.wildcard_photo_sync'))
                        <x-wildcard-photo class="me-md-0 me-3"
                                          id="navBarWildcardPhoto"
                                          :user="auth()->user()" />
                    @endif

                    <span class='nav-link'
                          data-cy="logged-in">{{ auth()->user()->full_name ?? auth()->user()->username }}</span>
                </div>
            </li>
            <li class='nav-item d-flex align-items-center'>
                @impersonating
                    <a class="nav-link" href="{{ route('impersonate.leave') }}">
                        Leave Impersonation
                        <i class="fas fa-sign-out-alt fa-fw ms-1" aria-hidden="true"></i>
                    </a>
                @else
                    <a class="nav-link"
                       data-cy="logout-link"
                       href="{{ route('logout') }}">
                        Sign out
                        <i class="fas fa-sign-out-alt fa-fw ms-1" aria-hidden="true"></i>
                    </a>
                @endImpersonating
            </li>
        @endauth

        @guest
            <li class='nav-item d-flex align-items-center'>
                <a class="nav-link" href="{{ route('login-selection') }}">
                    <i class="fas fa-sign-in-alt fa-fw me-1" aria-hidden="true"></i>
                    Sign in
                </a>
            </li>
        @endguest
    </ul>
</div>
