@extends('layouts.focus_minimenu', ['banner' => 'Identity Stored', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <span class="h4">Your Identity is <b>stored</b> on this client</span>
    <br>
    <br>
    <div class="bg-danger rounded text-white p-2 mb-4 fw-bold">⚠ Be sure to backup your private seed as this is the last time
        it will be visible.</div>
    <br>
    <br>
    <span class="h5">Your Visitor Id:</span>
    <div id="visitor_id" class="overflow-auto form-control" disabled readonly> {{ $visitor_id }} <a class="btn btn-outline-link btn-sm"
            onclick="copyToClipboard('{{ $visitor_id }}')" title="Copy to clipboard">📋</a> </div>
    <br>
    Seed (base64): <div id="base64_seed" class="overflow-auto form-control form-control-sm" disabled readonly> {{ $base64_seed }} </div>
    <br>
    <br>
    <span class="h5">Identity Backup</span>
    <br>
    
    <form class="mb-4 mt-1" method="POST">
        @csrf
        <button class="btn btn-outline-dark mt-1" type="submit" formaction="{{ domainRoute('download_identity', [
            'site_id' => $site_id,
            'visitor_id' => $visitor_id,
            'base64_seed' => $base64_seed,
            'alias' => $alias,
        ]) }}" class="btn btn-outline-success mb-1" 
        title="Download Backup">Download Backup</button>
    </form>
    <br>
    <br>
    <a class="btn btn-light fw-bold" style="width: 100%;" href="{{ \App\Http\H::siteUrl($target_site_id) . 'login' }}">
        Continue to Site and log in with you new identity</a>
    <br>
    <br>
    <br>
    <br>
@endsection
