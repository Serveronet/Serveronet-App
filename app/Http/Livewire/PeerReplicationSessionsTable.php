<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Models\PeerReplicationSession;
use App\Traits\HasCommonBulkActions;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class PeerReplicationSessionsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'peer_replication_sessions';
    protected $dataType = DataTypes::peer_replication_sessions;

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return PeerReplicationSession::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('session_id')
            ->add('client_address')
            ->add('current_page')
            ->add('per_page')
            ->add('total_records')
            ->add('state')
            ->add('last_completed_date')
            ->add('last_heartbeat')
            ->add('detailed_state')
            ->add('record_count_map')
            ->add('created_at')
        ;
    }

    public function columns(): array
    {
        return [
            Column::make('session_id', 'session_id')->searchable()->sortable(),
            Column::make('client_address', 'client_address')->searchable()->sortable(),
            Column::make('current_page', 'current_page')->searchable()->sortable(),
            Column::make('per_page', 'per_page')->searchable()->sortable(),
            Column::make('total_records', 'total_records')->searchable()->sortable(),
            Column::make('state', 'state')->searchable()->sortable(),
            Column::make('last_completed_date', 'last_completed_date')->searchable()->sortable(),
            Column::make('last_heartbeat', 'last_heartbeat')->searchable()->sortable(),
            Column::make('detailed_state', 'detailed_state')->searchable()->sortable(),
            Column::make('record_count_map', 'record_count_map')->searchable()->sortable(),
            Column::make('created_at', 'created_at')->searchable()->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
