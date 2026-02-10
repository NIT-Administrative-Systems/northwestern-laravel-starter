@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-3">
                <a class="text-decoration-none" href="{{ route('support.changelog.index') }}">
                    <i class="fas fa-arrow-left fa-fw me-1" aria-hidden="true"></i>All Entries
                </a>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ $entry->title ?? $entry->slug }}</strong>
                    <span class="text-muted small">{{ $entry->created_at->format('F j, Y') }}</span>
                </div>
                <div class="card-body">
                    <x-markdown :anchors="false">
                        {!! $entry->body !!}
                    </x-markdown>
                </div>
            </div>
        </div>
    </div>
@endsection
