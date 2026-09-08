@extends('layouts.app', ['banner' => 'Site Definitions', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <div class="p-2">
        @if ($site_id)
            <div class="h6">Showing Site Definitions for Site:
                <div class="overflow-auto">{{ $site_id }}</div>
            </div>
        @endif

        @livewire('site-definitions-table', ['site_id' => $site_id])
        <livewire:auto-refresh tableName="site_definitions" />
        <a href="#top" class="ml-1 underline">Top</a>
    </div>
@endsection
