@extends('layouts.app', ['banner' => 'Background Schedule Executions', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    @livewire('background-schedule-executions-table')

    <a href="#top" class="ml-1 underline">Top</a>

    <livewire:auto-refresh tableName="background_schedule_executions" />

    @include('client_root')
@endsection
