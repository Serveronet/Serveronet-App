<nav class="navbar navbar-expand-lg bg-light">
    <div class="container-fluid">
        @include('logo')
        <span>
            <a class="navbar-brand" href="{{ domainRoute('home') }}">Serveronet</a>
        </span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav">
                <x-nav-link :href="route('documentation', ['doc_tag' => 'installation_and_deployment'])" class="ml-1 underline" :active="request()->routeIs('*documentation')">Documentation</x-nav-link>
                <x-nav-link :href="domainRoute('demos')" class="ml-1 underline" :active="request()->routeIs('*demos')">Demos</x-nav-link>
                <x-nav-link :href="domainRoute('downloads')" class="ml-1 underline" :active="request()->routeIs('*downloads', '*all_downloads_listing')">Download</x-nav-link>
            </div>
        </div>
    </div>
</nav>
