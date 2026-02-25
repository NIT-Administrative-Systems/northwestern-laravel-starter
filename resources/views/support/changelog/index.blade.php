@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="font-poppins-bold mb-0">Changelog</h1>
                <x-clipboard :text="$feedUrl"
                             label="RSS Feed"
                             icon="fa-rss"
                             button-size="sm" />
            </div>

            @forelse ($entries as $entry)
                <div class="changelog-entry" id="{{ $entry->slug }}">
                    <div class="sticky-top small fw-semibold text-md-end align-self-start text-nowrap text-start"
                         style="top: 1.5rem; padding-top: 0.45em;">
                        <a class="text-decoration-none text-muted" href="{{ route('support.changelog.show', $entry) }}">
                            {{ $entry->created_at->format('F j, Y') }}
                        </a>
                    </div>

                    <div class="changelog-timeline d-none d-md-flex">
                        <div class="changelog-dot"></div>
                    </div>

                    <div class="align-self-start">
                        <h2 class="font-poppins-bold mb-3">
                            <a class="text-decoration-none text-dark" href="{{ route('support.changelog.show', $entry) }}">
                                {{ $entry->title ?? $entry->slug }}
                            </a>
                        </h2>

                        <div class="text-body">
                            <x-markdown :anchors="false" :options="['html_input' => 'escape']">
                                {!! $entry->body !!}
                            </x-markdown>
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-info">
                    <i class="fas fa-info-circle fa-fw me-1" aria-hidden="true"></i>
                    No changelog entries yet.
                </div>
            @endforelse

            {{ $entries->links() }}
        </div>
    </div>

    <style>
        .changelog-entry {
            display: grid;
            grid-template-columns: 10rem auto 1fr;
            gap: 0 1.25rem;
            padding-bottom: 3rem;
        }

        .changelog-entry:last-of-type {
            padding-bottom: 1.5rem;
        }

        .changelog-timeline {
            position: relative;
            display: flex;
            justify-content: center;
            width: 9px;
        }

        .changelog-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            border: 2px solid #fff;
            z-index: 1;
            margin-top: 0.75em;
            flex-shrink: 0;
        }

        /* Timeline vertical line */
        .changelog-timeline::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: -3rem;
            left: 50%;
            width: 1px;
            border-left: 1px dashed #d1d5db;
            transform: translateX(-50%);
        }

        .changelog-entry:last-of-type .changelog-timeline::after {
            display: none;
        }

        @media (max-width: 767.98px) {
            .changelog-entry {
                display: block;
                padding-bottom: 2.5rem;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 2.5rem;
            }

            .changelog-entry:last-of-type {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 1rem;
            }

            .changelog-entry> :nth-child(1) {
                text-align: left;
                padding-top: 0;
                position: static;
                margin-bottom: 0.25rem;
            }
        }
    </style>
@endsection
