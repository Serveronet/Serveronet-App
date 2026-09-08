@extends('layouts.central', ['banner' => 'All downloads ?with_dev=1',
'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites,
])

@section('content')

@livewire('published-versions-table', ['with_dev'=> $with_dev])

@include('client_root')

@endsection