@extends('layouts.guest', ['banner' => 'Serveronet Sites', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <a name="sites"></a>

    <livewire:sites-query-table />

    <a href="{{ domainRoute('guest_sites') . '#top' }}" class="ml-1 underline">Top</a>
@endsection
