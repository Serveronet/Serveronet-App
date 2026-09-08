@extends('layouts.app', ['banner' => 'Site Status Overview', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

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
    <div class="h4">Ongoing Replications</div>
    {{ $ongoingReplications }}
    <hr>
    <div class="h4">DB and Replication State</div>
    <br>
    site_definitions_replication_end_ts: {{ $site->site_definitions_replication_end_ts }}
    <br>
    site_peers_replication_end_ts: {{ $site->site_peers_replication_end_ts }}
    <br>
    visitor_records_replication_end_ts: {{ $site->visitor_records_replication_end_ts }}
    <br>
    visitor_resources_replication_end_ts: {{ $site->visitor_resources_replication_end_ts }}
    <hr>
    db_created: {{ tfyn($site->db_created) }}
    <br>
    db_schema_version: {{ $site->db_schema_version }}
    <br>
    <br>
    <br>
    <br>
    <br>
    <label class="h6" for="site_config_json">Site Config</label>
    <textarea name="site_config_json" class="form-control" id="site_config_json" rows="20">
        {{ $siteConfigJson ?? '' }}
    </textarea>
    <hr>
    <a href="#top" class="ml-1 underline">Top</a>
    </div>
@endsection
