@extends('layouts.app', ['banner' => 'Add Manually', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <br>
    <div class="h5">Add a Peer</div>
    <form class="ms-1 mb-4" method="POST" action="{{ domainRoute('add_manually_endpoint') }}">
        @csrf
        <div class="input-group">
            <span class="input-group-text">🌍</span>
            <input type="hidden" name="data_type" value="peer">
            <input type="text" class="form-control" name="client_address" value="{{ old('client_address') }}"
                id="client_address" placeholder="Peer Address (http://host:port/)">
        </div>
        <button class="btn btn-outline-warning mt-1" type="submit">Add Peer</button>
    </form>
    <br>
    <div class="h5">Add or Update Site using Seed</div>
    <form class="ms-1 mb-4" method="POST" action="{{ domainRoute('add_manually_endpoint') }}">
        @csrf
        <div class="input-group">
            <span class="input-group-text">🌍</span>
            <input type="hidden" name="data_type" value="site_by_seed">
            <input type="text" class="form-control" name="site_base64_seed" id="site_base64_seed"
                value="{{ old('site_base64_seed') }}" placeholder="Seed (base64)">
        </div>
        <button class="btn btn-outline-warning mt-1" type="submit">Add Site</button>
    </form>
    <div class="h5">Add Peer hosting a Site - Site Peer</div>
    <form class="ms-1 mb-4" method="POST" action="{{ domainRoute('add_manually_endpoint') }}">
        @csrf
        <div class="input-group mb-1">
            <span class="input-group-text">🌍</span>
            <input type="hidden" name="data_type" value="site_peer">
            <input type="text" class="form-control" name="site_id" id="site_id" value="{{ old('site_id') }}"
                placeholder="Site (hwy4phcb...) ">
        </div>
        <div class="input-group">
            <input type="text" class="form-control" name="client_address" id="client_address"
                value="{{ old('client_address') }}" placeholder="Peer Address (http://host:port/)">
        </div>
        <button class="btn btn-outline-warning mt-1" type="submit">Add Site Peer</button>
    </form>
    <br>
    <div class="h5">Add banned Site</div>
    <form class="ms-1 mb-4" method="POST" action="{{ domainRoute('add_manually_endpoint') }}">
        @csrf
        <div class="input-group mb-1">
            <span class="input-group-text">🌍</span>
            <input type="hidden" name="data_type" value="banned_site">
            <input type="text" class="form-control" name="site_id" id="site_id" value="{{ old('site_id') }}"
                placeholder="hwy4phcb...">
        </div>
        <div class="input-group">
            <input type="text" class="form-control" name="reason" id="reason" value="{{ old('reason') }}"
                placeholder="Optionally enter reason for banning">
        </div>
        <button class="btn btn-outline-warning mt-1" type="submit">Add Site</button>
    </form>
    <br>

    <div class="h5">Add domain - this client only.</div>
    <form class="ms-1 mb-4" method="POST" action="{{ domainRoute('add_manually_endpoint') }}">
        @csrf
        <div class="input-group mb-1">
            <span class="input-group-text">🌍</span>
            <input type="hidden" name="data_type" value="domain">
            <input type="text" class="form-control" name="site_id" id="site_id" placeholder="Site (hwy4phcb...) ">
        </div>
        <div class="input-group">
            <input type="text" class="form-control" name="domain" id="domain" value="{{ old('domain') }}"
                placeholder="domain-snet">
        </div>
        <button class="btn btn-outline-warning mt-1" type="submit">Add Serveronet Domain</button>
    </form>
    <br>
    <br>
@endsection
