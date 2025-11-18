@extends('northwestern::purple-container')

@php
    use App\Domains\User\Models\User;
@endphp

@section('content')
    <style>
        {{-- prettier-ignore --}}
        @keyframes fadeUp{0%{opacity:0;transform:translateY(20px)}100%{opacity:1;transform:translateY(0)}}.fade-up{animation:.9s cubic-bezier(.25,1,.3,1) fadeUp;will-change:opacity,transform;opacity:0;animation-fill-mode:forwards}.fade-up-delay-1{animation-delay:60ms}.fade-up-delay-2{animation-delay:.14s}.fade-up-delay-3{animation-delay:.24s}.fade-up-delay-4{animation-delay:.36s}.fade-up-delay-5{animation-delay:.5s}
    </style>
    <div class="row justify-content-center">
        <div class="col-sm-12 main-content text-center">
            <h1 class="slashes fade-up fade-up-delay-1"
                style="font-family: 'Poppins'; font-weight: 700; font-size: 2.7rem; margin-bottom: 0;">
                Northwestern Laravel Starter
            </h1>

            <p class="lead text-secondary fade-up fade-up-delay-2 pt-3">
                A comprehensive starter kit for Laravel projects at Northwestern University
            </p>

            <hr class="fade-up fade-up-delay-3 mb-4 mt-3"
                style="width: 90%; max-width: 500px; margin-left: auto; margin-right: auto;">

            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="d-grid d-sm-flex justify-content-sm-center fade-up fade-up-delay-4 gap-3">
                        <a class="btn btn-primary btn-lg px-4"
                           href="https://nit-administrative-systems.github.io/northwestern-laravel-starter"
                           aria-label="View Documentation"
                           target="_blank">
                            <i class="fas fa-book fa-fw me-2" aria-hidden="true"></i>
                            Documentation
                        </a>
                        <a class="btn btn-outline-secondary btn-lg px-4"
                           href="https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter"
                           aria-label="View on GitHub"
                           target="_blank">
                            <i class="fab fa-github fa-fw me-2" aria-hidden="true"></i>
                            GitHub
                        </a>
                    </div>
                </div>
            </div>

            <div class="text-muted small fade-up fade-up-delay-5 mt-5" style="font-size: 0.7rem">
                Built by <span style="color: rgb(118, 093, 160); font-weight: 600;">Northwestern IT &middot; Application
                    Development and Operations</span>
            </div>
        </div>
    </div>
@endsection
