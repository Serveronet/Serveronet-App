@extends('layouts.app', ['banner' => 'Peer Replication Sessions', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:peer-replication-sessions-table />

    <livewire:auto-refresh tableName="peer_replication_sessions" />

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
