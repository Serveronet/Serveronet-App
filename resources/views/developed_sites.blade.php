@extends('layouts.app', ['banner' => 'Developed Sites', 'help_tag' => \App\Dicts\DocsMapping::sites_development])

@section('content')
    @include('site_config_visual_editor_dialog', ['initial' => 'dummy'])


    <div class="ms-2">
        @if ($client_not_connectable ?? true)
            <div class="bg-warning mb-4 rounded text-white p-1 ps-2">This Client appears to be not connectable. Publishing
                might fail.</div>
        @endif
        <div class="h6">Place your Sites in the Developed Sites Directory</div>

        <div class="input-group mb-3">

            <input type="text" value="{{ storage_path('app/developed_sites') }}" class="form-control"
                id="developed_sites_directory">
            <a class="btn btn-outline-primary" onclick="copyToClipboard('{{ storage_path('app/developed_sites') }}')">📋</a>
        </div>
        <div class="input-group mb-1">
            <span class="h5 me-2">IPFS</span>
            <img src="{{ asset('sn_client_resources/img/Ipfs-logo-1024-ice-text.png', request()->isSecure()) }}"
                style="height:2em;" alt="">
        </div>
        <div>
            It's highly recommended to have IPFS operational when publishing a Serveronet site to increase availability.
        </div>

        <a href="https://ipfs.tech" target="_blank">https://ipfs.tech</a>
        <br>
        <br>
        <div class=""><a class="btn btn-outline-success btn-sm mb-1" target="_blank"
                href="{{ domainRoute('example_site_config') }}">Example site config</a></div>
    </div>
    <br>
    <hr>
    <br>
    Jump to
    <br>
    @foreach ($developed_sites_collection as $developed_site)
        <a class="btn btn-light btn-sm mb-1" href="#{{ $developed_site->site_id }}">#
            {{ Str::limit($developed_site->title, 35) }}</a>
    @endforeach

    <br>
    <br>
    <div class="h2">Developed Sites</div>

    @foreach ($developed_sites_collection as $developed_site)
        <a name="{{ $developed_site->site_id }}"></a>
        @include('error', ['site_id' => $developed_site->site_id])

        <form class="mb-4" method="POST" x-data="{ size: 10, isValidJson: true, site_config: {{ $developed_site->site_config_json }} }">
            <div x-init="localStorage.removeItem('json-text'); clicks=0"></div>
            @csrf
            <div class="p-2 bg-light bg-gradient rounded shadow-sm mb-2">
                <p>
                    <span class="fw-bold">Site Directory: </span><span class="fw-normal"
                        title="{{ storage_path('app/developed_sites/' . $developed_site->developed_site_dir) }}">{{ $developed_site->developed_site_dir }}

                        <a class="btn btn-outline-link btn-sm "
                            onclick="copyToClipboard('{{ storage_path('app/developed_sites/' . $developed_site->developed_site_dir) }}')"
                            title="Copy to clipboard">📋</a>
                        <a class="btn-sm link-underline-primary me-1"
                            href = "file://{{ storage_path('app/developed_sites/' . $developed_site->developed_site_dir) }}"
                            title="Copy path">DIR</a>

                    </span>
                    <span class="fw-bold">| Files: </span><span class="fw-normal">{{ $developed_site->files_count }}</span>
                    <span class="fw-bold">| Size: </span><span class="fw-normal"
                        title="{{ $developed_site->siteTotalSizeBytes }}">
                        {{ $developed_site->siteTotalSizeReadable }}</span>

                    @if ($developed_site->siteTotalSizeBytes > \App\Http\Consts::siteFilesMaxTotalSizeBytesServeronet)
                        <span class="badge rounded-pill text-bg-danger">⚠ Total maximum size exceed (10MB)</span>
                    @endif

                    @if ($developed_site->reservedDirPresent)
                        <span class="badge rounded-pill text-bg-danger">⚠ Reserved filename found
                            ({{ implode(', ', \App\Http\H::getReserveredPaths()) }})</span>
                    @endif

                </p>
                <p>
                    <span class="fw-bold">Latest published: </span>
                    <span class="fw-normal" title="{{ $developed_site?->earliest_site_definition?->entity_created ?? '' }}">
                        {{ $developed_site?->earliest_site_definition?->created_at ? 
                            \Illuminate\Support\Carbon::create($developed_site?->earliest_site_definition?->created_at ?: now())->diffForHumans() 
                            : 'Not published yet' }}
                    </span>
                </p>
                <p>
                <div class="overflow-auto">
                    <span class="p-1 pe-0">Site ID:</span>
                    <a class="btn btn-outline-link btn-sm" onclick="copyToClipboard('{{ $developed_site->site_id }}')"
                        title="Copy to clipboard">📋</a>
                        <span class="p-1">{{ $developed_site->site_id }}</span>
                        <input id="assigned_site_id" class="p-1 hidden" value="{{ $developed_site->site_id }}">
                </div>
                </p>

                <div class="input-group mb-1">
                    <span class="input-group-text">Title</span>
                    <input type="text" class="form-control" name="title" id="title"
                        value="{{ $developed_site->title }}" placeholder="Site title">
                </div>
                <div class="input-group mb-1">
                    <span class="input-group-text">Description</span>
                    <input type="text" class="form-control" name="description" id="description"
                        value="{{ $developed_site->description ?? '' }}" placeholder="Description">
                </div>

                <input type="hidden" class="form-control" name="developed_site_dir"
                    value="{{ $developed_site->developed_site_dir }}">
                <input type="hidden" class="form-control" name="site_id" value="{{ $developed_site->site_id }}">

                <div class="input-group mb-3">
                    @if ($developed_site->canBePublished)
                        <button type="submit" formaction="{{ domainRoute('publish_site') }}"
                            x-bind:disabled="!isValidJson" class="btn btn-outline-success m-1 rounded"
                            title="Hash files and publish new version of your Site">Publish Site 📢</button>
                    @else
                        <button type="button" disabled class="btn btn-outline-error">Can't publish</button>
                    @endif

                    @if ($developed_site->earliest_site_definition ?? null)
                        <a class="btn btn-info m-1 rounded" target="_blank"
                            href="{{ \App\Http\H::siteUrl($developed_site->site_id) }}"
                            title="Browse current, published version of your Site">Published Version 🌐</a>
                    @endif
                    <li class="nav-item dropdown btn btn-outline-success m-1 rounded">
                        <a class="nav-link dropdown-toggle ms-1" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            More
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <div class="mb-3 ms-3 mt-2 form-check">
                                    <input type="checkbox" class="form-check-input" id="add_self_as_trusted_site_peer"
                                        name="add_self_as_trusted_site_peer">
                                    <label class="form-check-label" for="add_self_as_trusted_site_peer">Add Self as
                                        Trusted Site Peer</label>
                                </div>

                            </li>
                            <li>
                                <a class="btn btn-outline-secondary m-1 @if (!$symlinkExists) disabled @endif"
                                    target="_blank" href="../developed_sites/{{ $developed_site->developed_site_dir }}"
                                    title="Preview raw version using local Apache server">Local Server Preview</a>
                            </li>
                            <li>
                                <button type="submit" formaction="{{ domainRoute('export_site_config_json') }}"
                                    class="btn btn-outline-danger m-1"
                                    title="Export Site Config to json file in Site's directory">Export Site Config</button>
                            </li>
                            <li>
                                <button type="submit" formaction="{{ domainRoute('prepare_sites_database') }}"
                                    class="btn btn-outline-success m-1"
                                    title="Prepare Site's database if not already created">Prepare or Upgrade database</button>
                            </li>
                        </ul>
                    </li>
                </div>
                <div class="fw-bold mb-1">Site Config</div>
                <button class="btn btn-sm btn-outline-dark mb-1" type="button"
                    x-on:click="
                    function () {
                      let nodeId = 'texarea_{{ $developed_site->site_id }}'
                      let current_site_config_json = document.getElementById(nodeId).value
                      current_site_config_json
                      let site_config = {}
                      site_config.initial_set_json = true
                      site_config.site_config_json = JSON.parse(current_site_config_json)
                      site_config._sn_site_id =  '{{ $developed_site->site_id }}'
                      let myIframe = document.getElementById('myIframe').contentWindow;        
                      myIframe.postMessage(site_config, '*');
                      let showButton2 = document.getElementById('favDialog'); showButton2.showModal();
                    }      
                    ">
                    Visual Editor 📑
                </button>
                <div class="btn btn-sm btn-outline-dark mb-1"
                    x-on:click="clicks++;size=document.getElementById('texarea_{{ $developed_site->site_id }}').value.split(/\r|\r\n|\n/).length + 5 + clicks;"
                    class="ml-1 underline">Resize</div>
                <span class="fw-bold">Valid Json:</span>
                <span class="fw-bold" x-text="isValidJson ? 'Valid 🙂' : 'Invalid 😡'"></span>
                <textarea x-bind:rows="size" name="site_config_json" class="form-control"
                    id="texarea_{{ $developed_site->site_id }}"
                    x-on:change="() => { try { JSON.parse(document.getElementById('texarea_{{ $developed_site->site_id }}').value);  } 
                    catch (e) { isValidJson = false; toastr.error('Invalid Json'); return; }  isValidJson = true;}"
                    rows="10">{{ $developed_site->site_config_json }}</textarea>
            </div>
            <a href="#top" class="ml-1 underline">Top</a>

            <script>
                window.addEventListener("message", (e) => {
                    if (e.data.react_app_update_json) {
                        let site_cofig = e.data
                        delete site_cofig.react_app_update_json
                        let site_config_json = JSON.stringify(site_cofig, null, 4);
                        let nodeId = 'texarea_' + site_cofig._sn_site_id
                        document.getElementById(nodeId).value = site_config_json;
                    }
                });
            </script>

        </form>
    @endforeach

    <br>
    <br>
    <br>
@endsection
