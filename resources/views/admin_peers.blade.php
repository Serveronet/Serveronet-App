@extends('layouts.app', ['banner' => 'Known Peers', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <div class="p-2">
        <livewire:peers-table />
        <livewire:auto-refresh tableName="peers" />
        <a href="#top" class="ml-1 underline">Top</a>
        <a href="{{ domainRoute('add_manually') }}" class="btn btn-success btn-sm float-end">Add</a>
    </div>
@endsection
