@extends('layouts.app', ['banner' => 'Internal Requests', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:internal-requests-table />

    <livewire:auto-refresh tableName="internal_requests" />

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
