@extends('northwestern::purple-container')

@section('heading')
    <h1 class="slashes pb-4 text-center">Enter verification code</h1>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">

            <div class="card rounded-3 border shadow-sm">
                <div class="card-body p-md-5 p-4">

                    <div class="mb-4 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary mb-3 bg-opacity-10"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-key fa-2x text-secondary" aria-hidden="true"></i>
                        </div>

                        <h2 class="h4 fw-semibold mb-2">
                            Check your email
                        </h2>
                        <p class="text-muted mb-0">
                            We sent an email to <strong>{{ $email }}</strong>. Enter the verification code below to
                            sign in.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('login-code.verify') }}"
                          novalidate>
                        @csrf

                        <div class="d-flex justify-content-center mb-4">
                            <x-otp name="code"
                                   :length="config('auth.local.code.digits', 6)"
                                   autofocus
                                   numeric />
                        </div>

                        @error('code')
                            <div class="text-danger small mb-3 text-center">
                                <i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>
                                {{ $message }}
                            </div>
                        @enderror

                        <button class="btn btn-primary btn-lg w-100 d-inline-flex align-items-center justify-content-center mb-3"
                                type="submit">
                            <i class="fas fa-lock-open fa-fw me-2" aria-hidden="true"></i>
                            <span>Verify</span>
                        </button>
                    </form>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                        <a class="text-decoration-none d-inline-flex align-items-center gap-1"
                           href="{{ route('login-code.request') }}">
                            <i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>
                            <span>Use a different email</span>
                        </a>

                        <form method="POST" action="{{ route('login-code.resend') }}">
                            @csrf
                            <button class="btn btn-link text-decoration-none p-0"
                                    id="resendBtn"
                                    type="submit"
                                    disabled>
                                Resend code
                            </button>
                            <span class="text-muted small ms-1" id="resendText"></span>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const btn = document.getElementById('resendBtn');
            const text = document.getElementById('resendText');
            const availableAt = Number(@json($resendAvailableAt)) * 1000;

            function tick() {
                const ms = availableAt - Date.now();
                if (ms <= 0) {
                    btn.disabled = false;
                    text.textContent = '';
                    return;
                }

                btn.disabled = true;
                text.textContent = `(${Math.ceil(ms / 1000)}s)`;
                requestAnimationFrame(() => setTimeout(tick, 250));
            }

            tick();
        })();
    </script>
@endpush
