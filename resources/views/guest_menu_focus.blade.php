<nav class="navbar navbar-expand-lg bg-light">
    <div class="container-fluid">
        @include('logo')
        <span>
            <a class="navbar-brand" href="{{ route('0' . getDomain() . 'home') }}">Serveronet</a>
        </span>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav">
                <x-nav-link :href="'./'" class="ml-1 underline" :active="request()->routeIs(getDomain() . 'home')">Visitor Home</x-nav-link>
            </div>
        </div>
    </div>
</nav>
