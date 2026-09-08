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
                <x-nav-link :href="domainRoute('admin_dashboard')" class="ms-1 me-1 underline" :active="domainRouteIs('admin_dashboard')">Admin Dashboard</x-nav-link>
                <x-nav-link :href="domainRoute('admin_sites')" class="ms-1 me-1" :active="domainRouteIs('admin_sites')">Sites</x-nav-link>
                <x-nav-link :href="domainRoute('control_panel')" class="ms-1 me-1 underline" :active="domainRouteIs('control_panel')">Control Panel</x-nav-link>
                <x-nav-link :href="domainRoute('settings')" class="ms-1 me-1 underline" :active="domainRouteIs('settings')">Settings</x-nav-link>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle ms-1" href="#" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        Various
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <x-nav-link :href="domainRoute('trackers')" class="ms-3 me-1" :active="domainRouteIs('trackers')">Torrent Trackers</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('admin_peers')" class="ms-3 me-1" :active="domainRouteIs('admin_peers')">Peers</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('admin_site_peers')" class="ms-3 me-1" :active="domainRouteIs('admin_site_peers')">Site Peers</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('resources')" class="ms-3 me-1" :active="domainRouteIs('resources')">Resources</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('replication_sessions')" class="ms-3 me-1" :active="domainRouteIs('replication_sessions')">Replication
                                Sessions</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('peer_replication_sessions')" class="ms-3 me-1" :active="domainRouteIs('peer_replication_sessions')">Peer Replication
                                Sessions</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('internal_requests')" class="ms-3 me-1" :active="domainRouteIs('internal_requests')">Internal
                                Requests</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('built_in_tracker')" class="ms-3 me-1" :active="domainRouteIs('built_in_tracker')">Built-in
                                Tracker</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('visitors')" class="ms-3 me-1" :active="domainRouteIs('visitors')">Visitors</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('passive_sessions')" class="ms-3 me-1" :active="domainRouteIs('passive_sessions')">Passive
                                Sessions</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('add_manually')" class="ms-3 me-1" :active="domainRouteIs('add_manually')">Add
                                Manually</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('background_schedule_executions_table')" class="ms-3 me-1"
                                :active="request()->routeIs('background_schedule_executions_table')">Background Schedule Executions</x-nav-link>
                        </li>
                        <li>
                            <x-nav-link :href="domainRoute('advanced_control_panel')" class="ms-3 me-1"
                                :active="request()->routeIs('advanced_control_panel')">Advanced Control Panel</x-nav-link>
                        </li>
                    </ul>
                </li>

                <x-nav-link :href="domainRoute('developed_sites')" class="ms-1 me-1 underline" :active="domainRouteIs('developed_sites')">Developed
                    Sites</x-nav-link>



                @if (Auth::guard('admin')->check())
                    <x-nav-link :href="domainRoute('admin_logout')" class="ms-1 me-1 underline">Admin Log Out</x-nav-link>
                @endif

                <x-nav-link :href="domainRoute('home')" class="ms-1 me-1 underline">Visitor Home</x-nav-link>
            </div>
        </div>
    </div>
</nav>
