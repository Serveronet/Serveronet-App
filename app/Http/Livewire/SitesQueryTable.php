<?php

namespace App\Http\Livewire;

use App\Http\H;
use App\Models\SiteDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Illuminate\Support\Str;

final class SitesQueryTable extends PowerGridComponent
{
    public string $sortField = 'sites.updated_at';

    public string $sortDirection = 'desc';

    public string $tableName = 'sites';

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
            PowerGrid::footer()->showPerPage()
                ->showRecordCount(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    |  Datasource
    |--------------------------------------------------------------------------
    | Provides data to your Table using a Eloquent, Query Builder or Collection
    |
    */

    /**
     * PowerGrid datasource.
     *
     * @return Builder
     */
    public function datasource(): ?Builder
    {
        $site_id = $this->site_id ?? null;

        $latestSiteDefinitions = DB::table('site_definitions')
            ->select('site_id', DB::raw('MAX(entity_created) as entity_created'))
            ->groupBy('site_id');

        $builder = DB::table('sites')
            ->select(['latest_site_definitions.*', 'sites.*', 'sites.site_id as sites_site_id'])
            ->where('visible_in_ui', true)
            ->addSelect(['latest_site_definitions_title' => SiteDefinition::select('title')
                ->whereColumn('site_definitions.site_id', 'sites.site_id')->orderByDesc('entity_created')->limit(1)])
            ->joinSub($latestSiteDefinitions, 'latest_site_definitions', function (JoinClause $join) {
                $join->on('sites.site_id', '=', 'latest_site_definitions.site_id');
            }, type: 'left')
            ->orderByDesc('created_at');

        if ($site_id)
            $builder->where('sites.site_id', $site_id);

        return $builder;
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
            ->add('is_pinned', fn($model) => $model->is_pinned ? '★' : '')
            ->add('site_id_formatted', function ($model) {
                return Str::limit(e($model->sites_site_id));
            })
            ->add('site_id', function ($model) {
                return e($model->sites_site_id);
            })
            ->add('site_link', function ($model) {
                return '<span style="background-color: #' . Str::limit(md5(e($model->sites_site_id)), 6, '') . '60;">&nbsp;&nbsp;&nbsp;</span>
                <a class="ms-1" href="' . e(H::siteUrl($model->sites_site_id)) . '">' .
                    e(Str::limit(($model->latest_site_definitions_title ?? $model->title ?? $model->title_draft ?? $model->sites_site_id ?? $model->site_id), 20)) . '</a>';
            })
            ->add('domain_link', function ($model) {
                return '<a  href="' . e(H::siteUrl($model->domain ?? $model->sites_site_id)) . '">' . e($model->domain) . '</a>';
            })
            ->add('site_admin', function ($model) {
                return '<a class="btn btn-link btn-sm mx-auto" title="Admin" href="' . e(domainRoute('admin_sites', ['site_id' => $model->site_id])) . '">Admin</a>';
            })
            ->add('site_id_lower', fn($model) => strtolower(e($model->sites_site_id)))
            ->add('is_published')
            ->add('title')
            ->add('title_formatted', function ($model) {
                return e($model->title);
            })
            ->add('title_draft')
            ->add('site_config')
            ->add('site_config_draft')
            ->add('is_hosted')
            ->add('ban_reason')
            ->add('is_registry_reported')
            ->add('published_at_formatted', fn($model) => $model->published_at ? Carbon::parse($model->published_at)->format('d/m/Y') : '')
            ->add('created_at_formatted', fn($model) => $model->sites_created_at ?? '' ?
                Carbon::parse($model->sites_created_at)->format('d/m/Y H:i:s') : '')
            ->add('updated_at_formatted', fn($model) =>  $model->updated_at ?? '' ?
                e(\Illuminate\Support\Carbon::create($model->updated_at)->diffForHumans()) : '')
            ->add('is_shell')
            ->add('trackers_site_peers_cached_at_formatted', fn($model) => $model->trackers_site_peers_cached_at ?
                Carbon::parse($model->trackers_site_peers_cached_at)->format('d/m/Y H:i:s') : '')
            ->add('domain')
            ->add('visitor_records_replication_end_ts_formatted', fn($model) => $model->visitor_records_replication_end_ts ?
                Carbon::parse($model->visitor_records_replication_end_ts)->format('d/m/Y H:i:s') : '')
            ->add('visitor_resources_replication_end_ts_formatted', fn($model) => $model->visitor_resources_replication_end_ts ?
                Carbon::parse($model->visitor_resources_replication_end_ts)->format('d/m/Y H:i:s') : '')
            ->add('last_self_hosting_trackers_announced_at_formatted', fn($model) => $model->last_self_hosting_trackers_announced_at ?
                Carbon::parse($model->last_self_hosting_trackers_announced_at)->format('d/m/Y H:i:s') : '')
            ->add('visible_in_ui')
            ->add('last_self_hosting_published_at_formatted', fn($model) => $model->last_self_hosting_published_at ?
                Carbon::parse($model->last_self_hosting_published_at)->format('d/m/Y H:i:s') : '')
            ->add('last_request_at_formatted', fn($model) => $model->last_request_at ? Carbon::parse($model->last_request_at)->format('d/m/Y') : '')
            ->add('description')
            ->add('description_draft')
            ->add('site_config_json_draft')
            ->add('is_to_be_hosted')
            ->add('site_definition_retrieval_count')
            ->add('uri', function ($model) {
                return '<a href="' . e(H::getUriLink($model)) . '">URI</a>';
            })
            ->add('dns_prefetch', function ($model) {
                return '<link rel="dns-prefetch" href="'.e(H::siteUrl($model->sites_site_id)).'" />';
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
                ->sortable('title')
                ->searchable(),

            Column::make('Domain', 'domain_link')
                ->field('domain_link', 'domain')
                ->sortable('domain_link')
                ->searchable(),

            Column::make('Description', field: 'description', dataField: 'description')
                ->sortable()
                ->searchable(),

            Column::make('Updated', 'updated_at_formatted', 'sites_updated_at')
                ->sortable(),

            Column::make('Uri', 'uri'),

            Column::make('Site Admin', 'site_admin'),

            Column::make('★', 'is_pinned')
                ->sortable(),

            Column::make('Site ID', field: 'site_id', dataField: 'site_id')
                ->sortable()
                ->searchable(),

            Column::make('Hidden title', 'title')
                ->searchable()
                ->hidden(),
            Column::make('', 'dns_prefetch'),
                
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
}
