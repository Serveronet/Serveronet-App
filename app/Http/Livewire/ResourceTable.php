<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Models\CachedResource;
use App\Traits\HasCommonBulkActions;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Illuminate\Support\Str;

final class ResourceTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'cached_resources';
    protected $dataType = DataTypes::cached_resources;

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
     * @return Builder<\App\Models\CachedResource>
     */
    public function datasource(): Builder
    {
        return CachedResource::query();
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
            ->add('sha256')
            ->add('ipfs_hash')
            ->add('file_size', function ($model) {
                return e(\App\Http\H::bytesCountToReadable($model->file_size ?? 0));
            })
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\'' .
                    e($model->id) . '\', \'cached_resources\')" >&nbsp;🗑&nbsp;</a>';
            })
            ->add('sha256_lower', fn(CachedResource $model) => e(strtolower($model->sha256)))
            ->add('sha256_limit', fn(CachedResource $model) => (e(Str::limit($model->sha256, 20))))
            ->add('created_at_formatted', fn(CachedResource $model) => $model->created_at ?
                Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '');
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

            Column::make('Sha256', 'sha256_limit', 'sha256')
                ->sortable()
                ->searchable(),

            Column::make(' ', '🗑'),

            Column::make('File Size', 'file_size')
                ->sortable(),

            Column::make('Sha256', 'sha256')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('ipfs_hash', 'ipfs_hash')
                ->sortable()
                ->searchable(),
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
            Filter::inputText('sha256')->operators(['contains']),
            Filter::inputText('ipfs_hash')->operators(['contains']),
            Filter::number('file_size'),
        ];
    }
}
