@extends('layouts.app', ['banner' => 'Visitors Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:visitors-table />

    <a href="#top" class="ml-1 underline">Top</a>

    <a href="{{ route('1' . getDomain() . 'register', ['site_id' => \App\Http\Consts::visitorControlPanelAddress]) }}"
        class="btn btn-success btn-sm float-end">Register</a>
@endsection
