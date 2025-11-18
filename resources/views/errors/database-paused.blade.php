@extends('northwestern::purple-container')

@php
    $page_title = 'Database Paused';
@endphp

@section('content')
    <div class="row justify-content-center min-vh-75 align-items-center">
        <div class="col-md-10 col-lg-8 col-xl-6">
            <div class="card border-warning">
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <div
                             class="d-inline-flex align-items-center justify-content-center bg-warning border-warnings mb-3 bg-opacity-10 p-3">
                            <i class="fas fa-database fa-2x text-warning" aria-hidden="true"></i>
                        </div>
                        <h1 class="h2 fw-bold text-dark mb-2">Database Paused</h1>
                    </div>

                    <div class="mb-4 p-4">
                        <p class="mb-0">
                            Our <span class="badge bg-secondary">{{ strtoupper(config('app.env')) }}</span>
                            environment uses an auto-scaling database that pauses during periods of inactivity
                            to optimize resource usage and costs.
                        </p>
                    </div>

                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <div class="spinner-border text-primary me-3"
                             role="status"
                             aria-hidden="true"
                             style="width: 1.5rem; height: 1.5rem;"></div>
                        <span class="text-muted">Database is starting up...</span>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
                    This page will automatically refresh in <span class="fw-semibold text-primary" id="countdown">30</span>
                    seconds
                </small>
            </div>
        </div>
    </div>

    @push('scripts')
        <script lang="text/javascript">
            let countdown = 30;
            const countdownElement = document.getElementById('countdown');

            const timer = setInterval(() => {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }

                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        </script>
    @endpush
@endsection
