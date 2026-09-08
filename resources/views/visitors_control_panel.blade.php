@extends('layouts.focus_minimenu', ['banner' => 'Visitor Control Panel Site', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <div class="ml-2">
        <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm row-cols-10">
            <div class="flex overflow-auto">
                <img class="pb-1" src="" id="avatar" style="height: 62px;" />
                <div class="mb-3 ms-1 p-1 fw-bold" style="color: #454444; height: 50px;  ">
                    <span class="fw-normal">Your Visitor ID:</span>
                    <br>
                    <span>{{ $visitor_id }}</span>
                </div>
                <script>
                    document.getElementById('avatar').src = generateAvatarSVG('{{ $visitor_id }}');
                </script>
            </div>
            <hr class="border border-success border-1 mt-4 mb-2" />

            <form action="{{ domainRoute('forget_visitor', ['site_id' => $site_id]) }}" method="POST">
                @csrf
                <div class="h3">Forget Visitor</div>
                <div class="h6">Your visitor and it's private seed will be removed from this client.</div>
                <div class="h6">They will be lost if not backed up.</div>
                <div class="h6">You will be able to register on a client again if private seed is known to you.</div>
                <div>Type this phraze to confirm account deletion: </div>
                <div class="fst-italic">{{ $requiredPhrase }}</div>
                <input type="text" class="form-control mt-3" name="required_phrase"
                    placeholder="Type required phrase here">
                <button type="submit" class="btn btn-outline-danger m-1">Forget Visitor</button>

            </form>

            <hr class="border border-success border-1 mt-4 mb-2" />

            <div class="h3">Access with Api Token</div>
            <div>Access with Api Token allows you to Post to the backend without being Authenticated with a password.</div>
            <div>This is designed for continuous automatic posting of updates.</div>
            <div>As this is not secure, enable only when you know what you are doing.</div>
            <div>See the
                <a
                    href="{{ domainRoute('documentation', ['doc_tag' => App\Dicts\DocsMapping::sites_development]) }}">documentation</a>
                on how to use.
            </div>
            <br>
            <form action="{{ domainRoute('toggle_api_token_upsert_endpoints', ['site_id' => $site_id]) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-warning m-1">
                    @if (Auth::guard('visitor')->user()->api_token)
                        Disable Api token
                    @else
                        Enable Api Token
                    @endif

                </button>
            </form>
            <br>
            <div class="p-2" style="overflow:auto;">

                <div>
                    Current Api Token:
                </div>
                <div>
                    <input class="form-control" type="text" value="{{ Auth::guard('visitor')->user()->api_token }}"
                        readonly>
                </div>
            </div>

            <hr class="border border-success border-1 mt-4 mb-2" />

            <div class="h3">Your Recent Identities</div>
            <div>Identities that were recently registered on this client.</div>
            <div>
                <a href="{{ domainRoute('get_recent_identities', ['site_id' => \App\Http\Consts::visitorControlPanelAddress]) }}"
                    class="btn btn-outline-success m-1">Recent Identities</a>
            </div>
            <hr class="border border-success border-1  mt-4 mb-2" />

            @if (Auth::guard('visitor')->check())
                <x-nav-link :href="domainRoute('logout', ['site_id' => $site_id])" class="ms-1 me-1">Visitor Log Out ↩</x-nav-link>
            @endif

            @if (!Auth::guard('visitor')->check())
                <x-nav-link :href="domainRoute('login', ['site_id' => $site_id])" class="ms-1 me-1">Visitor Login</x-nav-link>
            @endif
        </div>
    </div>
    <br>
    <br>
    <br>
    <br>
@endsection
