<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Http\Controllers\BackgroundProcessingController;
use App\Models\Peer;
use App\Models\SitePeer;
use App\Traits\HasCommonBulkActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class SitePeersTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'site_peers';

    protected $dataType = DataTypes::site_peers;

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
    public ?string $site_id;

    /**
     * PowerGrid datasource.
     *
     * @return Builder<SitePeer>
     */
    public function datasource(): ?Builder
    {
        $site_id = $this->site_id ?? null;
        $builder = SitePeer::query();

        if ($site_id) {
            $builder->where('site_id', $site_id);
        }

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
            ->add('site_id')
            ->add('source')
            ->add('site_id_lower', fn (SitePeer $model) => e(strtolower($model->site_id)))
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\''.e($model->id).'\', \'site_peers\')" >&nbsp;🗑&nbsp;</a>';
            })
            ->add('client_address')
            ->add('last_successfully_verified_at_formatted', function ($model) {
                return $model->last_successfully_verified_at ? Carbon::parse($model->last_successfully_verified_at)->format('d/m/Y H:i:s') : '';
            })
            ->add('created_at_formatted', fn (SitePeer $model) => $model->created_at ? Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('updated_at_formatted', fn (SitePeer $model) => $model->updated_at ? Carbon::parse($model->updated_at)->format('d/m/Y H:i:s') : '')
            ->add('archived_at_formatted', fn (SitePeer $model) => $model->archived_at ? Carbon::parse($model->archived_at)->format('d/m/Y H:i:s') : '');
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
            Column::make('Site id', 'site_id')
                ->sortable()
                ->searchable(),

            Column::make('Client address', 'client_address')
                ->sortable()
                ->searchable(),

            Column::make('Verified At', 'last_successfully_verified_at_formatted', 'last_successfully_verified_at')
                ->sortable(),

            Column::make('Source', 'source')
                ->sortable(),

            Column::make('Archived', 'archived_at_formatted', 'archived_at')
                ->sortable(),

            Column::action('Actions'),

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

    /*
    |--------------------------------------------------------------------------
    | Actions Method
    |--------------------------------------------------------------------------
    | Enable the method below only if the Routes below are defined in your app.
    |
    */

    /**
     * PowerGrid SitePeer Action Buttons.
     *
     * @return array<int, Button>
     */
    public function actions($row): array
    {
        return [
            Button::add('verify')
                ->slot('Verify')
                ->class('btn btn-outline-primary btn-sm')
                ->dispatch('verify', ['id' => $row->id]),
            Button::add('unArchive')
                ->slot('Un-Archive')
                ->class('btn btn-outline-primary btn-sm')
                ->dispatch('unArchive', ['id' => $row->id]),
        ];
    }

    #[On('verify')]
    public function verify($id)
    {
        $result = (new BackgroundProcessingController)->verifySitePeer($id);

        $this->dispatch(
            'show-popup',
            title: 'Site Peer Verification Attempt',
            html_content: 'Verification attempt successful: '.tfyn($result->getData()->data->verification_attempt_successful)
                .' <br> Hosting state: '.tfyn($result->getData()->data->state?->hosting_state)
                .' <br> Site is banned: '.tfyn($result->getData()->data->state?->site_is_banned)
                .' <br> Owner only: '.tfyn($result->getData()->data->state?->owner_only)
        );
    }

    #[On('unArchive')]
    public function unArchive($id)
    {
        $peer = SitePeer::find($id);
        $peer->archived_at = null;
        $peer->save();
        // $result = (new BackgroundProcessingController())->verifySitePeer($id);

        $this->dispatch(
            'show-popup',
            title: 'Unarchived',
            html_content: ''
        );
    }
}
