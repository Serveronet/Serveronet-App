@extends('layouts.focus_minimenu', ['banner' => 'adults_only_gate', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <div class="h1">This Site is for adults only!</div>
    <br>
    <div class="h4">If you are not an adult please be aware that some things cannot be unseen or unread.</div>
    <br>
    Press Continue to access this Site or Go back to Index
    <hr>
    <div class="h5">Site Title: {{ $siteDefinition->title }}</div>
    <br>
    <div class="h6">Site Description: {{ $siteDefinition->description }}</div>
    <br>
    <a href="{{ domainRoute('set_adults_only_access_cookie', ['site_id' => $siteDefinition->site_id]) }}">Continue</a>
    <br>
    <br>
    <a class="btn btn-success btn-lg" href="{{ domainRoute('guest_sites') }}">Go back to Index</a>
@endsection
