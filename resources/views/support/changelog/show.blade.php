@extends('northwestern::purple-container')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                <a class="text-muted text-decoration-none small"
                   href="{{ route('support.changelog.index') }}">
                    <i class="fas fa-arrow-left fa-fw me-1" aria-hidden="true"></i>All Entries
                </a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start pb-3 mb-3 border-bottom">
                        <h2 class="card-title font-poppins-bold mb-0 text-dark">
                            {{ $entry->title ?? $entry->slug }}
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
            </div>
        </div>
    </div>
@endsection
