@extends('layouts.app', ['banner' => 'Passive Sessions', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:passive-sessions-table />

    <livewire:auto-refresh tableName="passive_sessions" />

    <a href="#top" class="ml-1 underline">Top</a>
@endsection
