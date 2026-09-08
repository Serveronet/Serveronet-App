@extends('layouts.app', ['banner' => 'Built-in Tracker Info Hashes', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <livewire:local-tracker-info-hash-peers-table />
    <livewire:auto-refresh tableName="local_tracker_info_hash_peers" />
@endsection
