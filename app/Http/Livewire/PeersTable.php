<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Http\Controllers\ClientController;
use App\Models\Peer;
use App\Traits\HasCommonBulkActions;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\{PowerGrid as FacadesPowerGrid, Rule, RuleActions};
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Button, Column, Exportable, Footer, Header, PowerGridColumns, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Livewire\Attributes\On;

final class PeersTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'peers';

    protected $dataType = DataTypes::peers;

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
     * @return Builder<\App\Models\Peer>
     */
    public function datasource(): ?Builder
    {
        return Peer::query();
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
            ->add('client_address')
            ->add('reputation')
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\'' . e($model->id) . '\', \'peers\')" >&nbsp;🗑&nbsp;</a>';
            })
            ->add('client_address_lower', fn(Peer $model) => strtolower(e($model->client_address)))
            ->add('created_at_formatted', fn(Peer $model) => $model->created_at ?
                Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('last_connected_at', function ($model) {
                return $model->last_connected_at ? Carbon::parse($model->last_connected_at)->format('d/m/Y H:i:s') : '';
            })
            ->add('is_passive_client', function ($model) {
                return ty($model->is_passive_client);
            })
            ->add('last_inbound_connected_at')
            ->add('last_heartbeat_at')
            ->add('active_token')
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
            Column::make('Client address', 'client_address')
                ->sortable()
                ->searchable(),

            Column::make('Reputation', 'reputation')
                ->sortable(),

            Column::make('Last Connected', 'last_connected_at', 'last_connected_at'),

            Column::make(' ', '🗑'),

            Column::action('Checking'),

            Column::make('Last Inbound Connected', 'last_inbound_connected_at')
                ->sortable(),

            Column::make('Last Heartbeat', 'last_heartbeat_at')
                ->sortable(),

            Column::make('Is Passive', 'is_passive_client')
                ->sortable()->searchable(),

            Column::make('Active Token', 'active_token')
                ->sortable()->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),
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
     * PowerGrid Peer Action Buttons.
     *
     * @return array<int, Button>
     */


    public function actions($row): array
    {
        return [
            Button::add('check_peer')
                ->slot('Check')
                ->class('btn btn-outline-primary btn-sm')
                ->dispatch('check_peer', ['id' => $row->id]),
        ];
    }

    #[On('check_peer')]
    public function checkPeer($id)
    {
        $peer = Peer::find($id);
        $rc = (new ClientController())->checkPeerConnectivity(peer: $peer, do_inbound_connectivity_check: true);

        $html_content =
            'Check successful: ' . tfyn($rc->operation_successful)
            . ' <br> Connection attempt successful: ' . tfyn($rc->data['connection_attempt_successful'])
            . ' <br> Checking Inbound: ' . tfyn($rc->data['do_inbound_connectivity_check'])
            . ' <br> Inbound Connection attempt successful: ' . tfyn($rc->data['inbound_connectivity_successful'] ?? false);

        if ($rc->error_message)
            $html_content .= ' <br> Error Message, if any: <br>' . $rc->error_message;

        $this->dispatch(
            'show-popup',
            title: 'Site Peer Verification Attempt',
            html_content: $html_content
        );
    }
}
