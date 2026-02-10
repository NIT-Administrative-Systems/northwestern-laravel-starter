@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="slashes font-poppins-bold mb-0" style="font-size: 2rem;">Changelog</h1>
                <x-clipboard :text="$feedUrl"
                             label="RSS Feed"
                             icon="fa-rss"
                             button-size="sm" />
            </div>

            <p class="text-muted mb-4">A history of platform releases and updates.</p>

            @forelse ($entries as $entry)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ $entry->title ?? $entry->slug }}</strong>
                        <span class="text-muted small">{{ $entry->created_at->format('F j, Y') }}</span>
                    </div>
                    <div class="card-body">
                        <x-markdown :anchors="false">
                            {!! $entry->body !!}
                        </x-markdown>
                    </div>
                    <div class="card-footer text-muted small">
                        <a href="{{ route('support.changelog.show', $entry) }}">
                            <i class="fas fa-link fa-fw me-1" aria-hidden="true"></i>Permalink
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
