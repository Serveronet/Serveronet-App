@extends('layouts.central', ['banner' => 'Downloads', 'help_tag' => App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <div class="h6">Important: Read documentation before use</div>
    <a href="{{ route('documentation', ['doc_tag' => 'documentation_index']) }}">Installation and Deployment</a>
    <br>
    <br>
    <div class="">
        <div class="m-1">
            <a class="btn btn-outline-success" href="{{ domainRoute('windows_client_bundle.zip') }}">
                Windows Bundle
                <br>
                <img style="width:10em;" src="{{ asset('sn_client_resources/img/windows.png', request()->isSecure()) }}"
                    alt="" srcset="">
            </a>
            <div>For Windows</div>

        </div>
        <br>
        <div class="m-1">

            <a class="btn btn-outline-success" href="{{ domainRoute('linux_and_mac_client_bundle.zip') }}">
                Linux and Mac Bundle
                <br>
                <img style="width:10em;" src="{{ asset('sn_client_resources/img/ubuntu.png', request()->isSecure()) }}"
                    alt="" srcset="">
            </a>
            <div>For Linux (Ubuntu, Debian, Raspbian) and Mac</div>

        </div>

    </div>
    <hr>
    <br>
    <br>
    <div class="h5">Advanced Downloads</div>
    <div>Server bundle (For: Shared Hosting, VPS) - See documentation on how to deploy</div>
    <a class="btn btn-outline-success" href="{{ domainRoute('server_bundle.zip') }}">Server Bundle</a>

    <br>
    <br>
    <div>Manual Client Update</div>
    <a class="btn btn-sm btn-outline-success" href="{{ domainRoute('client_update.zip') }}">Client Update</a>
    <br>
    <hr>
    <div class="text-secondary small overflow-auto"><code>{{ $windows_client_bundle_version->label }} - Ver:
            {{ $windows_client_bundle_version->version }} | SHA256: {{ $windows_client_bundle_version->sha256 }}</code>
    </div>
    <div class="text-secondary small overflow-auto"><code>{{ $linux_and_mac_client_bundle->label }} - Ver:
            {{ $linux_and_mac_client_bundle->version }} | SHA256: {{ $linux_and_mac_client_bundle->sha256 }}</code></div>
    <div class="text-secondary small overflow-auto"><code>{{ $server_bundle->label }} - Ver:
            {{ $server_bundle->version }} | SHA256: {{ $server_bundle->sha256 }}</code></div>
    <div class="text-secondary small overflow-auto"><code>{{ $client_update->label }} - Ver:
            {{ $client_update->version }} | SHA256: {{ $client_update->sha256 }}</code></div>
    <br>
@endsection
