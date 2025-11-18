@php use App\Domains\User\Models\User; @endphp
@extends('northwestern::purple-container')

@php
    $page_title = 'Error';
@endphp

@section('content')
    <div class="row">
        <div class="col-xs-12 col-sm-7 col-md-6 main-content">
            <div class="alert alert-danger text-start" role="alert">
                <h2 class="alert-heading text-center">Something went wrong</h2>
                <p>Please wait for a moment and try again. If the problem persists, please contact the <b><a
                           href="https://www.it.northwestern.edu/support/service-desk/" target="_blank">IT Service
                            Desk</a></b> for assistance.</p>

                {{-- Add the Sentry error ID if there is a corresponding Sentry event (we can trace this if there is a screenshot in a report) --}}
                @if (app()->bound('sentry') && app('sentry')->getLastEventId())
                    <hr>
                    <p>If you'd like to help, please tell us what happened below:</p>
                    <form id="errorReportForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="nameInput">Name</label>
                            <input class="form-control"
                                   id="nameInput"
                                   type="text"
                                   value="{{ auth()->user()->full_name ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="emailInput">Email</label>
                            <input class="form-control"
                                   id="emailInput"
                                   type="email"
                                   value="{{ auth()->user()->email ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="commentInput">What happened?</label>
                            <textarea class="form-control"
                                      id="commentInput"
                                      rows="3"></textarea>
                        </div>
                        <button class="btn btn-primary d-flex align-items-center" type="submit">
                            <i class="fa fa-paper-plane me-2" aria-hidden="true"></i>
                            Submit Report
                        </button>
                    </form>
                    <div class="alert alert-success alert-dismissible fade mt-3"
                         id="feedbackAlert"
                         role="alert"
                         style="display: none;">
                        Your feedback has been submitted. Thank you!
                        <button class="btn-close"
                                data-bs-dismiss="alert"
                                type="button"
                                aria-label="Close"></button>
                    </div>
                    <p class="mb-0 mt-2">
                        <small><b>Error ID:</b> {{ app('sentry')->getLastEventId() }}</small>
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection

@if (app()->bound('sentry') && app('sentry')->getLastEventId())
    @push('scripts')
        <script lang="text/javascript">
            window.onload = function() {
                const form = document.getElementById('errorReportForm');
                form.addEventListener('submit', function(event) {
                    event.preventDefault();

                    const userFeedback = {
                        event_id: '{{ app('sentry')->getLastEventId() }}',
                        name: document.getElementById('nameInput').value,
                        email: document.getElementById('emailInput').value,
                        comments: document.getElementById('commentInput').value,
                    };

                    Sentry.captureUserFeedback(userFeedback);

                    const feedbackAlert = document.getElementById('feedbackAlert');
                    feedbackAlert.style.display = '';
                    feedbackAlert.classList.add('show');

                    form.reset();
                });
            }
        </script>
    @endpush
@endif
