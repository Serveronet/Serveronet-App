@extends('layouts.app', ['banner' => 'Sites Administration: ' . App\Http\H::getShortSiteID($site_id), 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <livewire:site-header />

    <livewire:site-actions-panel />

    @livewire('sites-admin-table', ['site_id' => $site_id])

    <div class="ml-1">
        <div style="display:flex;align-items:center;gap:6px;font-size:14px;">
            <input type="checkbox" id="allRecords" style="width:14px;height:14px;">
            <label for="allRecords" style="cursor:pointer;">Show All</label>
        </div>
        <script>
            allRecords.onchange = e => Livewire.dispatch('set_filter_status')
        </script>
    </div>

    <livewire:auto-refresh tableName="sites" />

    <a href="#top" class="ml-1 underline">Top</a>

    @include('client_root')
@endsection
