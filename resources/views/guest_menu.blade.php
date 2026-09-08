@if (App\Http\H::isFirstRunCompleted() &&
        request()->routeIs('admin_login') != 1 &&
        request()->routeIs('login') != 1 &&
        request()->routeIs('register') != 1 &&
        request()->routeIs('identity_created') != 1 &&
        request()->routeIs('visitor_control_panel') != 1 &&
        request()->routeIs('get_recent_identities') != 1)

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

                    <x-nav-link :href="domainRoute('home')" class="ml-1 underline" :active="domainRouteIs(getDomain() . 'home')">Visitor Home</x-nav-link>

                    <x-nav-link :href="domainRoute('guest_sites')" class="ml-1 underline" :active="request()->routeIs(getDomain() . 'guest_sites')">Sites</x-nav-link>

                    <x-nav-link :href="route('documentation', ['doc_tag' => 'documentation_index'])" class="ml-1 underline" :active="request()->routeIs('*documentation')">Documentation</x-nav-link>

                    <x-nav-link :href="\App\Http\H::siteUrl(\App\Http\Consts::visitorControlPanelAddress)" class="ml-1 underline">Visitor Control Panel</x-nav-link>
                    
                    <x-nav-link :href="domainRoute('admin_dashboard')" class="ml-1 underline" :active="domainRouteIs('admin_dashboard')">Client Admin</x-nav-link>


                    @if (request()->isSecure())
                        <x-nav-link :href="\App\Http\H::httpSwitcherUrls()['http']" class="ml-1 underline font-monospace">[HTTP]</x-nav-link>
                    @else
                        <x-nav-link :href="\App\Http\H::httpSwitcherUrls()['https']" class="ml-1 underline font-monospace">[🔒HTTPS]</x-nav-link>
                    @endif

                </div>
            </div>
        </div>
    </nav>

@endif

@if (App\Http\H::isFirstRunCompleted() &&
        (request()->routeIs('visitor_control_panel') || request()->routeIs('get_recent_identities')))
    <nav class="navbar navbar-expand-lg bg-light">
        <div class="container-fluid">
            @include('logo')
            <span>
                <a class="navbar-brand" href="{{ config('app.url') }}">Serveronet</a>
            </span>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav">

                    <x-nav-link :href="config('app.url')" class="ml-1 underline" :active="request()->routeIs('home')">Visitor Home</x-nav-link>
                    <x-nav-link :href="route(getDomain() . 'visitor_control_panel', [
                        'site_id' => \App\Http\Consts::visitorControlPanelAddress,
                    ])" class="ml-1 underline">Visitor Control Panel</x-nav-link>

                </div>
            </div>
        </div>
    </nav>
@endif
