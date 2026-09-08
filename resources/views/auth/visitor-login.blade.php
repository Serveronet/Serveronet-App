@extends('layouts.focus_minimenu', ['banner' => 'Site Login', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    @include ('site_specific_background')
    <div class="border-bottom border-4 border-danger mb-1">Login to the Site:</div>
    <div class="h4">{{ $site_title }}</div>
    <div class="">({{ $site_id }}) </div>
    <br>
    <br>

    <div id="appRecentIdentity">

        @if (\App\Http\H::inSingleSiteMode(request()))
            <script>
                recentVisitors = {!! $recentVisitors !!}
                recentIdentitiesMessage = {
                    'recent_identities': {!! $recentVisitors !!}
                }
                self.postMessage(recentIdentitiesMessage, '*')
            </script>
        @else
            <a class="ms-0 rounded-pill "
                href="{{ domainRoute('get_recent_identities', ['site_id' => \App\Http\Consts::visitorControlPanelAddress]) }}"
                target="_blank">Your Recent Identities</a>
            <iframe
                src="{{ domainRoute('get_recent_identities', ['site_id' => \App\Http\Consts::visitorControlPanelAddress]) }}"
                width="1px" height="1px" frameborder="0" id="recent_identities_iframe"></iframe>
        @endif

        <div class="d-flex flex-wrap mb-1">
            <div v-for="recentVisitor in visitors" :key="recentVisitor.visitor_id"
                @click="recentIdentitySelected(recentVisitor.visitor_id)" class="btn rounded-pill d-flex me-1 mb-1"
                v-bind:style="'background-color:'+ recentVisitor.color+'; color: white;'"
                v-bind:title="recentVisitor.visitor_id">
                <span class="fw-bold me-1 text-light">@{{ recentVisitor.alias }}</span>
                <span class="fw-bold me-1 text-shadow">@{{ recentVisitor.short }}</span>

                <a class="ms-2 badge rounded-pill text-bg-secondary "
                    href="{{ domainRoute('get_recent_identities', ['site_id' => \App\Http\Consts::visitorControlPanelAddress]) }}"
                    target="_blank">X</a>
            </div>
        </div>
    </div>
    <br>
    <br>
    <br>

    <?php
    $loginRoute = domainRoute('login', ['site_id' => $site_id]);
    ?>

    <form method="POST" action="{{ $loginRoute }}">
        @csrf

        <!-- Visitor Id -->
        <div>
            <x-label for="visitor_id" :value="__('Visitor Id')" />

            <x-input id="visitor_id" class="form-control" type="text" name="visitor_id" :value="old('visitor_id')" required
                autofocus />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-label for="password" :value="__('Password')" />

            <x-input id="password" class="form-control" type="password" name="password" required value="{{ $password }}"
                autocomplete="current-password" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input checked id="remember_me" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm 
                    focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    name="remember">
                <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">

            <a class="underline text-sm text-gray-600 hover:text-gray-900 me-1"
                href="javascript:toastr.info('Register a new and specify the new password', 'Forgot a password', {timeOut: 20000})">
                {{ __('Forgot your password?') }}
            </a>

            <?php
            $registerRoute = domainRoute('register', ['site_id' => \App\Http\Consts::visitorControlPanelAddress, 'target_site_id' => $site_id]);
            ?>

            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ $registerRoute }}">
                Not registered?
            </a>
            <x-button class="ml-3">
                {{ __('Log in') }}
            </x-button>
        </div>
    </form>
@endsection
