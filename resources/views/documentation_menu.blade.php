@if (App\Http\H::isFirstRunCompleted() &&
        request()->routeIs('admin_login') != 1 &&
        request()->routeIs('login') != 1 &&
        request()->routeIs('register') != 1 &&
        request()->routeIs('identity_created') != 1)

    <div class="navbar-nav mb-3 bg-white">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle ps-3" href="#" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                Topics
            </a>

            <ul class="dropdown-menu">
                @foreach ($mapping as $key => $map)
                    <li>
                        <x-nav-link :href="route('documentation', ['doc_tag' => $key])" class="ms-2 me-2 underline"
                            :active="request()->routeIs('home')">{{ $map['label'] }}</x-nav-link>
                    </li>
                @endforeach
            </ul>
        </li>
    </div>

@endif
