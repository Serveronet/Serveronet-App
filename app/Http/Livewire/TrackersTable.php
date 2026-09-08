<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Models\Tracker;
use App\Traits\HasCommonBulkActions;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TrackersTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'trackers';

    protected $dataType = DataTypes::trackers;

    /*
    |--------------------------------------------------------------------------
    |  Features Setup
    |--------------------------------------------------------------------------
    | Setup Table's general features
    |
    */
    public function setUp(): array
    {
        $this->showCheckBox();

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

    /**
     * PowerGrid datasource.
     *
     * @return Builder<\App\Models\Tracker>
     */
    public function datasource(): ?Builder
    {
        return Tracker::query();
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
            ->add('url')
            ->add('url_lower', fn(Tracker $model) => strtolower(e($model->url)))
            ->add('last_connected_at_formatted', fn(Tracker $model) => $model->last_connected_at ?
                Carbon::parse($model->last_connected_at)->format('d/m/Y H:i:s') : '')
            ->add('fails_count')
            ->add('last_error', function ($model) {
                return '<span title="' . e($model->last_error) . '">' . (! $model->last_error ? '' : 'ERROR 🔎 ') . '</span>';
            })
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\'' . e($model->id) . '\', \'trackers\')" >&nbsp;🗑&nbsp;</a>';
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
            Column::make('Url', 'url')
                ->sortable()
                ->searchable(),

            Column::make('Last connected at', 'last_connected_at_formatted', 'last_connected_at')
                ->sortable(),

            Column::make('Fails count', 'fails_count'),

            Column::make('Last error', 'last_error'),

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
        return [];
    }
}
