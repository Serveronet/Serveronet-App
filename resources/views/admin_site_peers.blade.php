@extends('layouts.app', ['banner' => 'Site Peers', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    @livewire('site-peers-table', ['site_id' => $site_id])
    <livewire:auto-refresh tableName="site_peers" />
    <a href="#top" class="ml-1 underline">Top</a>
    <a href="{{ domainRoute('add_manually') }}" class="btn btn-success btn-sm float-end">Add</a>
@endsection
