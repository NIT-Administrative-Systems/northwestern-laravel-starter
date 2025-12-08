@extends('northwestern::purple-container')

@php
    $page_title = 'Access Restricted';
    $productionUrl = config('app.production_url');
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 main-content">
            <div class="card border-danger mb-5 border-2 shadow-sm">
                <div class="card-header bg-danger-subtle border-bottom-0 py-4 text-center">
                    <i class="fa fa-exclamation-triangle fa-3x text-danger mb-3" aria-hidden="true"></i>
                    <h1 class="h3 fw-bolder text-danger mb-0">
                        Access Restricted
                    </h1>
                </div>

                <div class="card-body p-md-5 p-4">
                    <div class="text-center">
                        <p class="lead fw-semibold text-dark">
                            You do not have permission to access this environment.
                        </p>

                        <p class="text-muted mb-4">
                            This is the
                            <span class="badge bg-primary-subtle text-primary fw-bold text-uppercase p-1">
                                {{ strtoupper(config('app.env')) }}
                            </span>
                            environment for
                            <strong class="text-dark">{{ config('app.name') }}</strong>, which is strictly reserved for
                            <strong class="text-dark">Northwestern IT</strong> development and testing purposes.
                        </p>
                    </div>

                    <div class="rounded-3 bg-light my-4 border p-3">
                        <h2 class="h6 fw-bold text-secondary mb-2">
                            <i class="fa fa-question-circle me-1" aria-hidden="true"></i> Why are you seeing this?
                        </h2>
                        <p class="small text-secondary mb-0">
                            You do not have an assigned role that grants you access to this environment.
                            If you believe this is an error, please reach out to your project contact or the
                            <b>
                                <a class="text-primary fw-semibold"
                                   href="https://www.it.northwestern.edu/support/service-desk/"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    IT Service Desk
                                </a>
                            </b> for assistance.
                        </p>
                    </div>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="small text-muted mb-3">
                            If you arrived here by mistake, please use the link below to access the live environment.
                        </p>
                        <a class="btn btn-primary px-5" href="{{ $productionUrl }}">
                            <i class="fa fa-arrow-right me-2" aria-hidden="true"></i>
                            Go to Production Environment
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
