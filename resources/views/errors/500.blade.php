@php
    use App\Domains\Auth\Enums\PermissionEnum;
@endphp

@extends('northwestern::purple-container')

@php
    $page_title = 'Error';

    /**
     * Exception Detail Visibility Logic
     *
     * In non-production environments, we unconditionally display exception details to streamline the debugging process.
     * Analysts and developers often test using standard user accounts that lack administrative permissions. Since some
     * of these users may not have access to backend monitoring tools like Sentry, exposing the exception details
     * directly in the UI helps them infer the nature of the failure and facilitates more accurate issue reports.
     *
     * In production, visibility is strictly limited to users with the MANAGE_ALL permission (typically administrators).
     * This safeguards sensitive system information from being exposed to the general user base, while still granting
     * administrators immediate access to stack traces. This allows for rapid diagnosis of live issues without having
     * to cross-reference external logs or Sentry data.
     */
    $isProduction = app()->environment('production');
    $userCanViewDetails = auth()->check() && auth()->user()->can(PermissionEnum::MANAGE_ALL);

    $showDetails = !$isProduction || $userCanViewDetails;
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 main-content">

            <div class="card border-danger mb-5">
                <div class="card-header bg-danger py-3 text-center text-white">
                    <h2 class="h4 fw-bold mb-0 text-white">
                        <i class="fa fa-exclamation-triangle me-2"></i> Something went wrong
                    </h2>
                </div>

                <div class="card-body p-md-5 p-4">
                    <div class="text-center">
                        <p class="lead">Please wait for a moment and try again.</p>
                        <p class="text-muted">
                            If the problem persists, please contact the
                            <b><a class="text-decoration-none"
                                   href="https://www.it.northwestern.edu/support/service-desk/"
                                   target="_blank">
                                    IT Service Desk <i class="fa fa-external-link-alt fa-xs"></i>
                                </a></b> for assistance.
                        </p>
                    </div>

                    @if (app()->bound('sentry') && app('sentry')->getLastEventId())
                        <hr class="my-4">

                        <div class="alert alert-light border">
                            <h5 class="h6 fw-bold text-secondary mb-3">
                                <i class="fa fa-comment-dots me-1"></i> Help us fix this
                            </h5>
                            <p class="small text-muted">If you'd like to help, please tell us what happened below:</p>

                            <form id="errorReportForm">
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold small text-uppercase" for="nameInput">Name</label>
                                        <input class="form-control"
                                               id="nameInput"
                                               type="text"
                                               value="{{ auth()->user()->full_name ?? '' }}"
                                               placeholder="Your Name">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-bold small text-uppercase"
                                               for="emailInput">Email</label>
                                        <input class="form-control"
                                               id="emailInput"
                                               type="email"
                                               value="{{ auth()->user()->email ?? '' }}"
                                               placeholder="name@northwestern.edu">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-uppercase" for="commentInput">What
                                        happened?</label>
                                    <textarea class="form-control"
                                              id="commentInput"
                                              style="resize: none;"
                                              rows="4"
                                              placeholder="Describe what you were doing when the error occurred..."></textarea>
                                </div>

                                <div class="d-grid d-md-flex justify-content-md-end gap-2">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fa fa-paper-plane me-2" aria-hidden="true"></i>
                                        Submit Report
                                    </button>
                                </div>
                            </form>

                            <div class="alert alert-success alert-dismissible fade show mt-3"
                                 id="feedbackAlert"
                                 role="alert"
                                 style="display: none;">
                                <strong><i class="fa fa-check-circle"></i> Success!</strong> Your feedback has been
                                submitted. Thank you!
                                <button class="btn-close"
                                        data-bs-dismiss="alert"
                                        type="button"
                                        aria-label="Close"></button>
                            </div>
                        </div>
                    @endif

                    @if ($showDetails && isset($exception))
                        <div class="mt-5">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="fw-bold text-secondary mb-0">
                                    <i class="fa fa-terminal me-2"></i> Technical Details
                                </h5>

                                @if ($isProduction)
                                    <span class="badge bg-danger border-danger border text-white shadow-sm">
                                        <i class="fa fa-lock me-1"></i> Administrators Only
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark border-warning border shadow-sm">
                                        <i class="fa fa-eye-slash me-1"></i> Non-Production Only
                                    </span>
                                @endif
                            </div>

                            <div class="card border-secondary bg-white shadow-sm">
                                <div class="card-body p-3">
                                    <div class="mb-3">
                                        <label class="fw-bold text-uppercase text-muted small"
                                               style="font-size: 0.7rem;">Error Message</label>
                                        <div
                                             class="border-danger bg-light text-danger font-monospace text-break fw-bold border border-2 p-3">
                                            <i class="fa fa-times-circle me-2"></i> {{ $exception->getMessage() }}
                                        </div>
                                    </div>

                                    <details>
                                        <summary
                                                 class="btn btn-dark btn-sm w-100 d-flex justify-content-between align-items-center text-start">
                                            <span><i class="fa fa-code me-2"></i> View Full Stack Trace</span>
                                            <i class="fa fa-chevron-down small"></i>
                                        </summary>
                                        <div class="mt-2">
                                            <pre class="bg-dark text-light mb-0 border p-3 shadow-inner"
                                                 style="font-size: 0.75rem; max-height: 400px; overflow: auto; white-space: pre-wrap; font-family: 'Consolas', 'Monaco', monospace;">{{ $exception->getTraceAsString() }}</pre>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @if (app()->bound('sentry') && app('sentry')->getLastEventId())
                    <div class="card-footer bg-light py-3 text-center">
                        <small class="text-muted fw-bold font-monospace text-uppercase">
                            <i class="fa fa-fingerprint text-secondary me-1"></i>
                            Error ID: {{ app('sentry')->getLastEventId() }}
                        </small>
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection

@if (app()->bound('sentry') && app('sentry')->getLastEventId())
    @push('scripts')
        <script lang="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('errorReportForm');
                if (form) {
                    form.addEventListener('submit', function(event) {
                        event.preventDefault();

                        const btn = form.querySelector('button[type="submit"]');
                        const originalBtnContent = btn.innerHTML;

                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Sending...';

                        const userFeedback = {
                            associatedEventId: '{{ app('sentry')->getLastEventId() }}',
                            name: document.getElementById('nameInput').value,
                            email: document.getElementById('emailInput').value,
                            message: document.getElementById('commentInput').value,
                        };

                        try {
                            Sentry.captureFeedback(userFeedback);

                            const feedbackAlert = document.getElementById('feedbackAlert');
                            feedbackAlert.style.display = 'block';

                            btn.classList.remove('btn-primary');
                            btn.classList.add('btn-success');
                            btn.innerHTML = '<i class="fa fa-check me-2"></i> Sent';
                            form.reset();
                        } catch (e) {
                            console.error(e);
                            btn.disabled = false;
                            btn.innerHTML = originalBtnContent;
                            alert('Failed to send report. Please try again.');
                        }
                    });
                }
            });
        </script>
    @endpush
@endif
