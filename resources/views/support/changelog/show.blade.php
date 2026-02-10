@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10 pt-3">
            <div class="changelog-show-layout">
                <div class="align-self-start">
                    <div class="sticky-top small fw-semibold text-md-end text-nowrap text-start" style="top: 1.5rem;">
                        <a class="text-muted text-decoration-none" href="{{ route('support.changelog.index') }}">
                            <i class="fas fa-arrow-left fa-fw me-1" aria-hidden="true"></i>Changelog
                        </a>
                        <div class="text-muted mt-3">
                            {{ $entry->created_at->format('F j, Y') }}
                        </div>
                    </div>
                </div>

                {{-- Empty column to match index page timeline spacing --}}
                <div class="d-none d-md-block"></div>

                <div>
                    <div class="d-flex justify-content-between align-items-start border-bottom mb-4 pb-3">
                        <h2 class="font-poppins-bold text-dark mb-0">
                            {{ $entry->title ?? $entry->slug }}
                        </h2>

                        <x-clipboard :text="route('support.changelog.show', $entry)"
                                     label="Share"
                                     icon="fa-link"
                                     button-size="sm" />
                    </div>

                    <div class="text-body">
                        <x-markdown :anchors="false">
                            {!! $entry->body !!}
                        </x-markdown>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .changelog-show-layout {
            display: grid;
            grid-template-columns: auto 9px 1fr;
            gap: 0 1.25rem;
        }

        @media (max-width: 767.98px) {
            .changelog-show-layout {
                display: block;
            }
        }
    </style>
@endsection
