@extends('layouts.app', ['banner' => 'Replication Sessions', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:replication-sessions-table />

    <livewire:auto-refresh tableName="replication_sessions" />

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
