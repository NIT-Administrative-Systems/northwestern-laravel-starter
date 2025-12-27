@extends('northwestern::purple-container')

@section('heading')
    <h1 class="slashes pb-4 text-center">Sign in</h1>
@endsection

@section('content')
    <div class="row g-4 g-lg-5 justify-content-center align-items-stretch">

        {{-- Northwestern SSO --}}
        <div class="col-12 col-md-6 col-lg-5">
            <article class="card h-100 border shadow-sm">
                <div class="card-body d-flex flex-column p-md-5 p-4 text-center">

                    <div class="mb-4">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-user-graduate fa-2x text-primary" aria-hidden="true"></i>
                        </div>
                    </div>

                    <p class="text-uppercase text-muted small fw-semibold mb-1">
                        single sign-on
                    </p>

                    <h2 class="card-title h4 fw-bold mb-3">
                        Northwestern Community
                    </h2>

                    <p class="text-muted flex-grow-1 mb-4">
                        For students, faculty, staff, and affiliates with a NetID.
                    </p>

                    <a class="btn btn-primary btn-lg w-100 d-inline-flex align-items-center justify-content-center mt-auto"
                       data-cy="netid-login"
                       href="{{ route('login-oauth-redirect') }}">
                        <i class="fas fa-sign-in-alt fa-fw me-2" aria-hidden="true"></i>
                        <span>Sign in with NetID</span>
                    </a>
                </div>
            </article>
        </div>

        {{-- External / Email-based sign-in --}}
        <div class="col-12 col-md-6 col-lg-5">
            <article class="card h-100 border shadow-sm">
                <div class="card-body d-flex flex-column p-md-5 p-4 text-center">

                    <div class="mb-4">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-10"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-envelope fa-2x text-secondary" aria-hidden="true"></i>
                        </div>
                    </div>

                    <p class="text-uppercase text-muted small fw-semibold mb-1">
                        Email-based access
                    </p>

                    <h2 class="card-title h4 fw-bold mb-3">
                        External Partners
                    </h2>

                    <p class="text-muted flex-grow-1 mb-4">
                        For approved external users and partners who do not have a NetID.
                    </p>

                    <a class="btn btn-outline-secondary btn-lg w-100 d-inline-flex align-items-center justify-content-center mt-auto"
                       data-cy="email-login"
                       href="{{ route('login-code.request') }}">
                        <i class="fas fa-paper-plane fa-fw me-2" aria-hidden="true"></i>
                        <span>Sign in with Email</span>
                    </a>
                </div>
            </article>
        </div>

    </div>
@endsection
