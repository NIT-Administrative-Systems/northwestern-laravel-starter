@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7">
            @if ($limitedSupportAlert)
                <div class="alert alert-warning mb-4">
                    <h2 class="h4">Limited Support</h2>
                    <p>Limited support for this environment is available. The contact form may behave differently
                        by sending emails instead of creating tickets in the IT ticketing system.</p>
                    <p class="mb-0">If you are performing testing for a project, please reach out to the project
                        team instead of using this form.</p>
                </div>
            @endif

            <div class="card rounded-3 border shadow-sm">
                <div class="card-body p-md-5 p-4">

                    <div class="mb-4">
                        <h1 class="h3 fw-semibold mb-3">Contact Support</h1>

                        <h2 class="h5 fw-semibold mb-2">Need help or have a question?</h2>
                        <p class="text-muted">
                            Have a question about something? By submitting the form below, you can contact the
                            <strong>Northwestern IT</strong> support team to discuss the matter.
                        </p>
                        <p class="text-muted">
                            Submitting this form will send your request directly to the support team.
                            You will receive a confirmation email, and a team member will follow up with
                            you as soon as possible.
                        </p>

                        <h2 class="h5 fw-semibold mb-2">Have an idea?</h2>
                        <p class="text-muted">
                            We welcome enhancement requests and feedback. Let us know what would make
                            this application work better for you.
                        </p>
                        <p class="text-muted mb-0">
                            Please be aware that new functionality and/or enhancements may be at the discretion of the
                            platform advisory group and any approved enhancement will be prioritized against other such
                            requests.
                        </p>
                    </div>

                    <hr class="mb-4">

                    <form method="POST"
                          action="{{ route('support.contact.store') }}"
                          novalidate>
                        @csrf

                        <div class="form-group mb-3">
                            <label class="form-label required" for="subject">Subject</label>
                            <input class="form-control @error('subject') is-invalid @enderror bg-white"
                                   id="subject"
                                   name="subject"
                                   type="text"
                                   value="{{ old('subject') }}"
                                   autocomplete="off"
                                   placeholder="I need help with..."
                                   maxlength="200"
                                   required>
                            @error('subject')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label required" for="details">Details</label>
                            <textarea class="form-control @error('details') is-invalid @enderror bg-white"
                                      id="details"
                                      name="details"
                                      aria-describedby="detailHint"
                                      rows="6"
                                      placeholder="Describe the issue or your idea..."
                                      required>{{ old('details') }}</textarea>
                            <small class="form-text text-muted" id="detailHint">
                                Please include relevant details such as what you were trying to do,
                                what happened, and any steps to reproduce the issue.
                            </small>
                            @error('details')
                                <div class="invalid-feedback d-block">
                                    <i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-lg btn-primary text-uppercase" type="submit">
                                <i class="far fa-paper-plane me-1" aria-hidden="true"></i>
                                Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
