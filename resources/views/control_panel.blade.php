@extends('layouts.app', ['banner' => 'Control Panel', 'help_tag' => App\Dicts\DocsMapping::client_administration])

@section('content')
<br>
    <div class="ml-2">
        <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm row-cols-10">
            
            <br>
            <div class="h4">Lists which provide banned sites</div>
            <div class="h6">Lists which Client Owner can enable to ban Sites</div>
            <form action="{{ 'save_enabled_lists' }}" method="POST">
                @csrf

                @forelse ($blockListSites as $site)
                    @foreach ($site->site_List_Providers as $list)
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                name="{{ $list->tag . '@' . $site->site_id }}"
                                {{ in_array($list->tag . '@' . $site->site_id, $enabledLists) ? 'checked' : '' }}>
                            <label class="form-check-label">{{ $list->description }} - {{ $list->data_type }}
                                {{ $list->tag . '@' }}<a target="_blank"
                                    href="{{ \App\Http\H::a(\App\Http\H::siteUrl($site->site_id)) }}">{{ $site->site_id }}</a></label>
                        </div>
                    @endforeach
                @empty
                    <p>There are no known Sites which provide a list of Sites</p>
                @endforelse
                @if (count($blockListSites) > 0)
                    <button class="btn btn-outline-primary" type="submit">Save and Update</button>
                @endif
            </form>
            <br>
            <hr>
            
            <br>
            <a name="app-url"></a>
            <div class="h4">URL used for internal calls</div>

            <form action="{{ 'set_app_url' }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="app_url" class="form-label">App Url.</label>
                    <label for="app_url" class="form-label form-control-sm">Adjust for specific needs. Otherwise leave a
                        default value.</label>

                    <input type="url" class="form-control" id="app_url" name="app_url"
                        value="{{ config('app.url') }}" placeholder="http://snet.localhost:15080" />
                </div>
                <button class="btn btn-outline-primary" type="submit">Save</button>

                <button class="btn btn-outline-secondary" type="submit" formaction="{{ 'check_internal_call' }}">Test
                    Internal Calls</button>
            </form>
            <br>
            <hr>
            <br>
            <a name="ui-addresses"></a>
            <div class="h4">UI Addresses</div>
            <form action="{{ 'set_ui_addresses' }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="ui_addresses" class="form-label">UI Addresses.</label>
                    <label for="ui_addresses" class="form-label form-control-sm">Add additional addresses if needed.
                        Separate each Url with a line break.</label>

                    <textarea rows="4" class="form-control" id="ui_addresses" name="ui_addresses"
                        placeholder="http://snet.localhost:15080">{{ $ui_addresses }}</textarea>
                </div>
                <button class="btn btn-outline-primary" type="submit">Save</button>
            </form>
            <br>
            <hr>
            <br>
            <a name="update_public_address"></a>
            <div class="h4">External URL</div>
            <form action="{{ 'update_public_address' }}" id="appExternalURL" method="POST">
                @csrf
                <div x-data="{ isPublicSelfAddressOverridden: {{ $isPublicSelfAddressOverridden }} }" class="mb-3">
                    <label for="publicSelfAddress" class="form-label">URL on which other peers will be able to connect to
                        this peer. </label>
                    <br>
                    <label for="publicSelfAddress" class="form-label">Auto generated based on detected external IP and
                        port forward.</label>
                    <br>
                    <label for="publicSelfAddress" class="form-label">No value means that external address was not
                        detected.</label>
                    <input type="url" class="form-control mb-1" id="publicSelfAddress" name="publicSelfAddress"
                        value="{{ $publicSelfAddress }}" placeholder="Example: https://public_ip:443/"
                        x-bind:disabled="!isPublicSelfAddressOverridden"
                        x-bind:readonly="!isPublicSelfAddressOverridden" />
                    <input class="form-check-input" type="checkbox" id="isPublicSelfAddressOverriddenInput"
                        x-on:click="isPublicSelfAddressOverridden = ! isPublicSelfAddressOverridden"
                        x-bind:checked="isPublicSelfAddressOverridden" name="isPublicSelfAddressOverriddenInput">
                    <label class="form-check-label ms-1" for="isPublicSelfAddressOverriddenInput">
                        Override - Disable autogeneration
                    </label>
                </div>
                <button class="btn btn-outline-primary" type="submit">Save</button>
            </form>
            <br>
            <hr>
            <br>
            <div class="h4">Main Loop</div>
            <form method="POST">
                @csrf
                <button class="btn btn-outline-success" type="submit"
                    formaction="{{ domainRoute('main_loop_requester', ['target' => 'control_panel']) }}">Start Main
                    Loop</button>
                <button class="btn btn-outline-danger" type="submit"
                    formaction="{{ domainRoute('signal_main_loop_stop', ['target' => 'control_panel']) }}">Signal Main
                    Loop Stop</button>
            </form>
            <br>
            <span>Main loop executed in last 2 minutes:
                <b>{{ $mailLoopStatus['mainLoopExecutedInLast2Minutes'] ? 'Yes' : 'No' }}</b></span>
            <br>

            <span>Main loop stop signaled: <b>{{ $mailLoopStatus['mainLoopStopSignaled'] ? 'Yes' : 'No' }}</b></span>
            <br>
            <span class="">Main Loop is</span>
            <span class="badge text-bg-{{ $mailLoopStatus['css'] }}">{{ $mailLoopStatus['label'] }}</span>
            <br>
            <br>
            
        </div>
    </div>
    <br>
    <br>
@endsection
