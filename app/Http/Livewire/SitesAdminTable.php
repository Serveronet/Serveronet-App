<?php

namespace App\Http\Livewire;

use App\Http\H;
use App\Models\ListProviderEntry;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Traits\HasReloadAction;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

final class SitesAdminTable extends PowerGridComponent
{
    use HasReloadAction;

    public string $tableName = 'sites';
    public string $sortField = 'updated_at';
    public string $sortDirection = 'desc';

    /*
    |--------------------------------------------------------------------------
    |  Features Setup
    |--------------------------------------------------------------------------
    | Setup Table's general features
    |
    */
    public function setUp(): array
    {
        // $this->showCheckBox();

        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage(20)
                ->showRecordCount(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    |  Datasource
    |--------------------------------------------------------------------------
    | Provides data to your Table using a Model or Collection
    |
    */

    public string|null $site_id;
    public string $filterStatus = 'visible_in_ui_only'; // Default value

    /**
     * PowerGrid datasource.
     *
     * @return Builder<\App\Models\Site>
     */
    public function datasource(): ?Builder
    {
        $site_id = $this->site_id ?? null;
        $latestSiteDefinitions = DB::table('site_definitions')
            ->select('site_id', DB::raw('MAX(entity_created) as entity_created'))
            ->groupBy('site_id');

        $builder = DB::table('sites')
            // ->select(['latest_site_definitions.*', 'sites.*', 'sites.site_id as sites_site_id', 'latest_site_definitions.title as latest_site_definitions_title'])
            ->select(['latest_site_definitions.*', 'sites.*', 'sites.site_id as sites_site_id'])
            ->when($this->filterStatus === 'visible_in_ui_only', fn($query) => $query->where('visible_in_ui', true))
            ->addSelect(['site_peers_count' => SitePeer::select(DB::raw('count(*)'))
                ->whereColumn('site_peers.site_id', 'sites.site_id')->limit(1)])
            ->addSelect(['latest_site_definitions_title' => SiteDefinition::select('title')
                ->whereColumn('site_definitions.site_id', 'sites.site_id')->orderByDesc('entity_created')->limit(1)])
            ->addSelect(['is_banned' => ListProviderEntry::select(DB::raw('count(*) > 0'))
                ->where('data_type', 'banned_sites')
                ->whereColumn('list_provider_entries.content', 'sites.site_id')->limit(1)])
            ->joinSub($latestSiteDefinitions, 'latest_site_definitions', function (JoinClause $join) {
                $join->on('sites.site_id', '=', 'latest_site_definitions.site_id');
            }, type: 'left')
            ->orderByDesc('created_at');

        if ($site_id)
            $builder->where('sites.site_id', $site_id);

        return $builder;
    }

    #[On('set_filter_status')]
    public function setFilterStatus(): void
    {
        $this->filterStatus = ($this->filterStatus == 'all') ? 'visible_in_ui_only' : 'all';
        $this->resetPage();
    }

    /*
    |--------------------------------------------------------------------------
    |  Relationship Search
    |--------------------------------------------------------------------------
    | Configure here relationships to be used by the Search and Table Filters.
    |
    */

    /**
     * Relationship search.
     *
     * @return array<string, array<int, string>>
     */
    public function relationSearch(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    |  Add Column
    |--------------------------------------------------------------------------
    | Make Datasource fields available to be used as columns.
    | You can pass a closure to transform/modify the data.
    |
    | ❗ IMPORTANT: When using closures, you must escape any value coming from
    |    the database using the `e()` Laravel Helper function.
    |
    */
    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('site_id', fn($model) => e($model->sites_site_id))
            ->add('title', function ($model) {
                return e($model->title);
            })
            ->add('site_id_formatted', function ($model) {
                return Str::limit(e($model->sites_site_id));
            })
            ->add('site_id_copy', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:copyToClipboard(\'' . e($model->sites_site_id) . '\')" >Copy Site ID</a>';
            })

            ->add('load_site_admin_actions', function ($model) {
                return '<button class="btn btn-outline-primary btn-sm"  wire:click="showActionsPanel(\'' . e($model->sites_site_id) . '\')">⚙</button>';
            })

            ->add('site_link', function ($model) {
                $h = ! $model->visible_in_ui ? '❌ ' : '';
                return '<span style="background-color: #' . Str::limit(e(md5($model->sites_site_id)), 6, '') . '60;">&nbsp;&nbsp;&nbsp;</span>
                <a class="ms-1" href="' . e(H::siteUrl($model->sites_site_id)) . '">' .
                    e($h .
                        Str::limit(($model->latest_site_definitions_title ?? $model->title ?? $model->title_draft ?? $model->sites_site_id ?? $model->site_id), 20)) . '</a>';
            })

            ->add('domain_link', function ($model) {
                return '<a  href="' . e(H::siteUrl($model->domain ?? $model->sites_site_id)) . '">' . e($model->domain) . '</a>';
            })

            ->add('requests_counter', function ($model) {
                return e($model->requests_counter);
            })
            ->add('last_request_at')
            ->add('files_count', function ($model) {
                return e($model->files_count ?? 0);
            })
            ->add('files_size', function ($model) {
                return \App\Http\H::bytesCountToReadable((e($model->files_size ?? 0)));
            })
            ->add('visitor_records_total_size', function ($model) {
                return \App\Http\H::bytesCountToReadable((e($model->visitor_records_total_size)));
            })
            ->add('visitor_records_count', function ($model) {
                return e($model->visitor_records_count);
            })
            ->add('visitor_resources_total_size', function ($model) {
                return \App\Http\H::bytesCountToReadable((e($model->visitor_resources_total_size)));
            })
            ->add('visitor_resources_count', function ($model) {
                return (e($model->visitor_resources_count));
            })
            ->add('peers_count', function ($model) {
                return e($model->site_peers_count) . '+' . e($model->trackers_site_peers_count);
            })
            ->add('is_banned', fn($model) => $model->is_banned ? '✓' : '')
            ->add('is_hosted', fn($model) => $model->is_hosted ? '✓' : '')
            ->add('download_state', function ($model) {
                return (e($model->download_state ?? '-'));
            })
            ->add('hosting_state_reason', function ($model) {
                return (e($model->hosting_state_reason) ? 'Hosting State reason: ' . e($model->hosting_state_reason) : '');
            })
            ->add('is_to_be_hosted', fn($model) => $model->is_to_be_hosted ? '✓' : '')
            ->add('is_pinned', fn($model) => $model->is_pinned ? '★' : '')
            ->add('site_id_lower', fn($model) => strtolower(e($model->sites_site_id)))
            ->add('published_at_formatted', fn($model) => $model->published_at ? Carbon::parse($model->published_at)->format('d/m/Y') : '')
            ->add('created_at_formatted', fn($model) => $model->created_at ? Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('updated_at_formatted', fn($model) => $model->updated_at ? Carbon::parse($model->updated_at)->format('d/m/Y H:i:s') : '')
            ->add('description')
            ->add('uri', function ($model) {
                return '<a href="' . e(H::getUriLink($model)) . '">URI</a>';
            })
            ->add('info_hash', function ($model) {
                return e(sha1($model->sites_site_id));
            })
        ;
    }

    /*
    |--------------------------------------------------------------------------
    |  Include Columns
    |--------------------------------------------------------------------------
    | Include the columns added columns, making them visible on the Table.
    | Each column can be configured with properties, filters, actions...
    |
    */

    /**
     * PowerGrid Columns.
     *
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [

            Column::make(title: 'Site', field: 'site_link', dataField: 'title')
                ->sortable()
                ->searchable(),

            Column::make(title: 'Domain', field: 'domain_link', dataField: 'domain')
                ->sortable()
                ->searchable(),

            Column::make('Admin', 'load_site_admin_actions'),

            Column::make('Hosted', 'is_hosted'),

            Column::make('Is to be hosted', 'is_to_be_hosted'),

            Column::make('Files', 'files_count'),

            Column::make(title: 'Size', field: 'files_size', dataField: 'files_size')
                ->sortable(),

            Column::make('V-Recs', 'visitor_records_total_size')
                ->sortable(),

            Column::make('V-Recs C', 'visitor_records_count')
                ->sortable(),

            Column::make('V-Files', 'visitor_resources_total_size')
                ->sortable(),

            Column::make('V-Files C', 'visitor_resources_count')
                ->sortable(),

            Column::make('Requests', field: 'requests_counter', dataField: 'requests_counter')
                ->sortable(),

            Column::make('Last Request', 'last_request_at')
                ->sortable(),

            Column::make('Peers', 'peers_count'),

            Column::make('Banned', 'is_banned'),

            Column::make('Downloaded', 'download_state'),

            Column::make('hosting_state_reason', 'hosting_state_reason'),

            Column::make('Pinned', 'is_pinned'),

            Column::make('Published at', 'published_at_formatted', 'published_at')
                ->sortable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('Updated at', 'updated_at_formatted', 'updated_at')
                ->sortable(),

            Column::make('Site ID', field: 'site_id', dataField: 'sites.site_id')
                ->sortable()
                ->searchable(),

            Column::make('Uri', 'uri'),
            Column::make('Info Hash', 'info_hash'),

            Column::make('Site ID', 'site_id_copy'),
        ];
    }

    /**
     * PowerGrid Filters.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }

    public function showActionsPanel($site_id)
    {
        $this->dispatch('show_action_panel', $site_id);
        $this->dispatch('action-start');
    }
}
