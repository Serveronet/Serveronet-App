@extends('layouts.app', ['banner' => 'Visitor Resources Hosting Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    Site ID:
    <div class="overflow-auto">{{ $site->site_id }}</div>
    <br>
    <livewire:site-visitor-resources site_id="{{ $site->site_id }}" />

    <br>

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
