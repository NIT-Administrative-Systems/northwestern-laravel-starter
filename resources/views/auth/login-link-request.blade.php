@extends('northwestern::purple-container')

@section('heading')
    <h1 class="slashes pb-4 text-center">Sign in with Email</h1>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">

            <div class="card rounded-3 border shadow-sm">
                <div class="card-body p-md-5 p-4">

                    <div class="mb-4 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary mb-3 bg-opacity-10"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-envelope-open-text fa-2x text-secondary" aria-hidden="true"></i>
                        </div>

                        <h2 class="h4 fw-semibold mb-2">
                            Request a sign-in link
                        </h2>
                        <p class="text-muted mb-0">
                            Enter the email address associated with your account to receive a secure sign-in link.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('login-link.send') }}"
                          novalidate>
                        @csrf

                        <div class="form-floating mb-4">
                            <input class="form-control form-control-lg @error('email') is-invalid @enderror bg-white"
                                   id="email"
                                   name="email"
                                   type="email"
                                   value="{{ old('email') }}"
                                   required
                                   autofocus
                                   autocomplete="off">
                            <label for="email">Email</label>

                            @error('email')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <button class="btn btn-primary btn-lg w-100 d-inline-flex align-items-center justify-content-center mb-3"
                                type="submit">
                            <i class="fas fa-paper-plane fa-fw me-2" aria-hidden="true"></i>
                            <span>Send sign-in link</span>
                        </button>

                        <div class="text-center">
                            <a class="text-decoration-none d-inline-flex align-items-center gap-1"
                               href="{{ route('login-selection') }}">
                                <i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>
                                <span>Back to sign-in options</span>
                            </a>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
