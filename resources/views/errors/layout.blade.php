@props(['title' => 'Error'])

@php
    $page_title = $title;
@endphp

@extends('northwestern::purple-container')

@section('content')
    <div class="row">
        <div class="col-sm-12 main-content text-center">
            <h1>{{ $title }}</h1>

            {{-- The main error message goes into the default slot --}}
            <p class="h5 text-secondary pt-3">
                {{ $slot }}
            </p>

            <div class="d-grid col-xs-12 col-sm-7 col-md-6 col-lg-4 col-xl-3 mx-auto pt-3">
                <a class="btn btn-primary" href="/">Back to Homepage</a>
            </div>
        </div>
    </div>
@endsection
