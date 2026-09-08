@extends('layouts.app', ['banner' => 'Site Peers Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <div class="h6">
        Site:
        <div class="overflow-auto">
            {{ $site->site_id }}
            <a
                href="{{ \App\Http\H::a(\App\Http\H::siteUrl($site->site_id)) }}">{{ $site->most_recent_site_definition->title ?? '' }}</a>
        </div>
    </div>

    <br>

    <div class="h4">Site Peers:</div>
    @foreach ($sitePeers as $sitePeer)
        <div class="ms-2">
            {{ $sitePeer->client_address . ' | Archived: ' . tfyn($sitePeer->archived_at) }}
        </div>
    @endforeach

    @if (count($sitePeers) == 0)
        No Site peers
    @endempty
    <hr>

    <div class="h4">Peer Mix:</div>
    @foreach ($peerMix as $peerMixSitePeer)
        <div class="ms-2">
            {{ $peerMixSitePeer->client_address . ' | Archived: ' . tfyn($peerMixSitePeer->archived_at) }}
        </div>
    @endforeach
    <hr>
    <a href="#top" class="ml-1 underline">Top</a>
    </div>
@endsection
