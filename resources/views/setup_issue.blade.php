@extends('layouts.focus', ['banner' => "$issue", 'help_tag' => \App\Dicts\DocsMapping::client_administration])

@section('content')
    <div class="h3">{{ $issue }}</div>
    <br>
    <br>
    <div class="h6">
        <a href="{{ $relavant_link }}">Relavant link</a>
    </div>
@endsection
