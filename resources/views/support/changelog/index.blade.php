@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h1 class="font-poppins-bold mb-0">Change Log</h1>
                <x-clipboard :text="$feedUrl"
                             label="RSS Feed"
                             icon="fa-rss"
                             button-size="sm" />
            </div>

            <p class="text-muted mb-4">A history of platform releases and updates.</p>

            @forelse ($entries as $entry)
                <div class="card shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start pb-3 mb-3 border-bottom">
                            <h2 class="card-title font-poppins-bold mb-0">
                                <a class="text-decoration-none text-dark"
                                   href="{{ route('support.changelog.show', $entry) }}">
                                    {{ $entry->title ?? $entry->slug }}
                                </a>
                            </h2>
                            <span class="badge bg-light text-muted border ms-3 flex-shrink-0">
                                {{ $entry->created_at->format('M j, Y') }}
                            </span>
                        </div>

                        <div class="text-body">
                            <x-markdown :anchors="false">
                                {!! $entry->body !!}
                            </x-markdown>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top-0 px-4 pb-3 pt-0">
                        <a class="text-muted small text-decoration-none"
                           href="{{ route('support.changelog.show', $entry) }}">
                            <i class="fas fa-arrow-right fa-fw me-1" aria-hidden="true"></i>Permalink
                        </a>
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
@endsection
