@extends('layouts.app', ['banner' => 'Resources Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:resource-table />

    <a href="#top" class="ml-1 underline">Top</a>

    @include('client_root')
@endsection
