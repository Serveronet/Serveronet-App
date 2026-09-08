<?php

namespace App\Http\Livewire;

use App\Models\SiteDefinition;
use App\Traits\HasReloadAction;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class SiteDefinitionsTable extends PowerGridComponent
{
    use HasReloadAction;

    public string $tableName = 'site_definitions';

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
    | Provides data to your Table using a Model or Collection
    |
    */

    public string|null $site_id;
    /**
     * PowerGrid datasource.
     *
     * @return Builder<\App\Models\SiteDefinition>
     */
    public function datasource(): ?Builder
    {
        $site_id = $this->site_id ?? null;

        $queryBuilder = SiteDefinition::query();

        if ($site_id)
            $queryBuilder->whereSiteId($site_id);

        return $queryBuilder;
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
            ->add('site_id_lower', fn(SiteDefinition $model) => e(strtolower($model->site_id)))
            ->add('entity_created')
            ->add('download_state')
            ->add('title')
            ->add('description')
            ->add('files_count')
            ->add('files_size', function ($model) {
                return e(\App\Http\H::bytesCountToReadable(($model->files_size ?? 0)));
            })
            ->add('visitor_records_preliminary_total_size')
            ->add('visitor_resources_preliminary_total_size')
            ->add('created_at_formatted', fn(SiteDefinition $model) => $model->created_at ? Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\'' . e($model->id) . '\', \'site_definitions\')" >&nbsp;🗑&nbsp;</a>';
            });
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
            Column::make('Download state', 'download_state')
                ->sortable()
                ->searchable(),

            Column::make('Created', 'entity_created')
                ->sortable()
                ->searchable(),

            Column::make('Title', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable(),

            Column::make('Files count', 'files_count'),

            Column::make('Files size', 'files_size'),

            Column::make('Visitor records preliminary total size', 'visitor_records_preliminary_total_size'),

            Column::make('Visitor resources preliminary total size', 'visitor_resources_preliminary_total_size'),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make(' ', '🗑'),
        ];
    }

    /**
     * PowerGrid Filters.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [
            Filter::inputText('site_id')->operators(['contains']),
            Filter::inputText('entity_created')->operators(['contains']),
            Filter::inputText('download_state')->operators(['contains']),
            Filter::inputText('title')->operators(['contains']),
            Filter::datetimepicker('created_at'),
        ];
    }
}
