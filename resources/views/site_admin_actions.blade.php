@if ($site)

    <div class="d-flex mb-1 overflow-auto" x-data="{ in_progress: false }" x-transition>

        @include('session')
        @include('error')
        <div class="m-1 p-2 rounded sn-action-min col-1">
            <div class="mb-1 sn-action-min-height">Ban Site</div>
            <form class="" wire:submit="editSiteState('{{ \App\Dicts\Tags::banning }}')"
                @click="$dispatch('action-start')">

                <button class="btn btn-outline-danger btn-sm col-12" type="submit">
                    {{ $site->list_entries->sum('is_manual_entry') > 0 ? 'Unban' : 'Ban' }}
                </button>
                <input class="mt-1 rounded border pt-1 pb-1 col-12" wire:model="ban_reason" type="text"
                    placeholder="Ban reason" />
            </form>
        </div>

        <div class="vr"></div>
        <div class="m-1 p-2 rounded sn-action-min">
            <div class="mb-1 sn-action-min-height">Hosting target State</div>
            <form wire:submit="editSiteState('{{ \App\Dicts\Tags::hosting }}')" @click="$dispatch('action-start')">
                <button class="btn btn-outline-success btn-sm col-12" type="submit">
                    {{ $site->is_to_be_hosted ? 'To Unhost' : 'To be Hosted' }}
                </button>
            </form>
        </div>

        <div class="vr"></div>
        <div class="m-1 p-2 rounded sn-action-min">
            <div class="mb-1 sn-action-min-height">Pin</div>
            <form wire:submit="editSiteState('{{ \App\Dicts\Tags::pinning }}')" @click="$dispatch('action-start')">
                <button class="btn btn-outline-warning btn-sm col-12" type="submit">
                    {{ !$site->is_pinned ? 'Pin' : 'Un Pin' }}
                </button>
            </form>
        </div>

        <div class="vr"></div>
        <div class="m-1 p-2 rounded sn-action-min">
            <div class="mb-1 sn-action-min-height">Update Site</div>
            <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_updating }}')"
                @click="$dispatch('action-start')">
                <button class="btn btn-outline-secondary btn-sm col-12" type="submit">
                    Update Site
                </button>
            </form>
        </div>

        @if (!$advanced_section_visible)
            <button class="btn btn-outline-light btn-sm col-1 ms-1" wire:click="showAdvanced">
                <div class="fs-1">…</div>
                <div class="text-secondary">Advanced</div>
                <div class="text-secondary">Actions</div>
            </button>
        @endif

        @if ($advanced_section_visible)

            <div class="vr"></div>
            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('site_hosting_overview', ['site_id' => $site->site_id]) }}">Files
                    Overview</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('site_visitor_resources_overview', ['site_id' => $site->site_id]) }}">Visitor
                    Files</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('admin_site_definitions', ['site_id' => $site->site_id]) }}">Site
                    Definitions</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('site_status_overview', ['site_id' => $site->site_id]) }}">Status
                    Overview</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('admin_site_peers', ['site_id' => $site->site_id]) }}">Site
                    Peers</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <a class="btn btn-link btn-sm col-12" target="_blank"
                    href="{{ domainRoute('site_peers_overview', ['site_id' => $site->site_id]) }}">Tracker
                    and Site Peers Overview</a>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Announce to Trackers</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_announce }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-secondary btn-sm col-12" type="submit" @disabled(!$site->is_hosted)>
                        @if ($site->is_hosted)
                            Announce
                        @else
                            Not Hosted
                        @endif
                    </button>
                </form>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Reset Visitor data replication</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_reset_visitor_data_replication_state }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-primary btn-sm col-12" type="submit">
                        Reset replication
                    </button>
                </form>
            </div>
            <div class="vr"></div>

            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Republish Site</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_republish }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-primary btn-sm col-12" type="submit">
                        Republish Site
                    </button>
                </form>
            </div>
            <div class="vr"></div>
            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Debug: Recreate DB</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_recreate_db }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-primary btn-sm col-12" type="submit">
                        Recreate DB
                    </button>
                </form>
            </div>
            <div class="vr"></div>                

            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Debug: Force Toggle as Hosted</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::debug_toggle_site_hosted }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-primary btn-sm col-12" type="submit">
                        Toggle Hosted
                    </button>
                </form>
            </div>
            
            <div class="m-1 p-2 rounded sn-action-min">
                <div class="mb-1 sn-action-min-height">Purge Site</div>
                <form wire:submit="editSiteState('{{ \App\Dicts\Tags::site_deleting }}')"
                    @click="$dispatch('action-start')">
                    <button class="btn btn-outline-primary btn-sm col-12" type="submit">
                        Purge Site
                    </button>
                </form>
            </div>
        @endif
    </div>

@endif
