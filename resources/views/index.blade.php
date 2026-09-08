@extends('layouts.guest', [
    'banner' => 'Serveronet ≈ P2P Websites',
    'help_tag' => \App\Dicts\DocsMapping::about_serveronet,
])

@section('content')
    <br>
    <div class="h4 text-dark">Welcome to the Serveronet Client</div>
    <div class="h6 text-secondary">Free and uncensorable Decentralized Cryptographic P2P Network of Sites</div>
    <br>
    <br>

    <div class="fs-5">
        Explore known Serveronet Sites
    </div>
    <div class="fs-6">

        <a class="btn btn-light btn shadow-sm rounded-1 btn-lg" wire:navigate.hover
            href="{{ domainRoute('guest_sites') }}">Sites →</a>
    </div>
    @if (config('sn.serve_owner_only'))
        <br>
        <div class="fs-5">
            This is a private client.
            <br>
            Download your client from <a
                href="https://{{ App\Http\Consts::serveronetSiteAddress }}/">{{ App\Http\Consts::serveronetSiteAddress }}</a>
        </div>
    @endif
    <br>
    <br>

    <form action="{{ domainRoute('go_to_site') }}" method="POST">
        @csrf
        <div class="fs-6" x-data="{}">
            Got a Site ID?
            <span class="btn btn-outine-light btn-sm"
                @click="
    toastr.info(document.getElementById('partials.welcome-tooltip').innerHTML, 'Addresses supported', {timeOut: 20000})
    "
                class="text-dark dark:text-white pre text-decoration-none small">
                <img style="width: 1em;"
                    src="{{ asset('sn_client_resources/img/help_FILL0_wght200_GRAD-25_opsz48.svg', request()->isSecure()) }}"
                    alt="" srcset="">
            </span>
        </div>
        <div class="input-group">
            <input type="text" class="form-control" style="max-width: 30em;" name="multi_input" id="multi_input"
                placeholder="hwy4phcb...">
            <button class="btn btn-warning" type="submit">GO</button>
        </div>
    </form>
    <br>
    <br>
    <br>
    <br>
    <div class="fs-4">
        Documentation
    </div>
    <div class="fs-6">
        Find out more about Serveronet in the <a class="btn btn-outline-secondary"
            href="{{ domainRoute('documentation', ['doc_tag' => \App\Dicts\DocsMapping::about_serveronet]) }}">Documentation
            →</a>
    </div>
    <br>
    <br>
    <br>
    <br>
    <hr>
    <div class="small">
        Administrator of this client can re-run "First Run setup" by deleting <i>fist_run_completed.php</i> file in
        storage/app/ directory.
    </div>

    <div class="hidden" id="partials.welcome-tooltip">
        @include('partials.welcome-tooltip')
    </div>

    @if ($main_loop_bootstrap_enabled)
        <script>
            var xhttp = new XMLHttpRequest();
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            xhttp.open("POST", "./start_main_loop_endpoint", true);
            xhttp.setRequestHeader("X-CSRF-TOKEN", csrfToken);
            xhttp.send();
        </script>
    @endif
@endsection
