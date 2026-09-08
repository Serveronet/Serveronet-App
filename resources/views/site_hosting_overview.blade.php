@extends('layouts.app', ['banner' => 'Site Resource Hosting Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    Site ID:
    <div class="overflow-auto">{{ $site_id }}</div>
    <br>
    <livewire:site-resources-overview-table site_id="{{ $site_id }}" />

    <br>

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
