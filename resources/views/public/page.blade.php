@extends('layouts.public')

@section('content')
    <article>
        <h1>{{ $page->title }}</h1>
        {{ $page->html() }}
    </article>
@endsection
