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
            ->add('progress', fn(PeerReplicationSession $model) => (int) $model->total_records > 0
                ? min(100, (int) round((int) $model->current_page * (int) $model->per_page / (int) $model->total_records * 100)) . '%'
                : '')
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
            Column::make('Session ID', 'session_id')->searchable()->sortable(),
            Column::make('Client Address', 'client_address')->searchable()->sortable(),
            Column::make('Current Page', 'current_page')->searchable()->sortable(),
            Column::make('Per Page', 'per_page')->searchable()->sortable(),
            Column::make('Total Records', 'total_records')->searchable()->sortable(),
            Column::make('Progress', 'progress'),
            Column::make('State', 'state')->searchable()->sortable(),
            Column::make('Last Completed Date', 'last_completed_date')->searchable()->sortable(),
            Column::make('Last Heartbeat', 'last_heartbeat')->searchable()->sortable(),
            Column::make('Detailed State', 'detailed_state')->searchable()->sortable(),
            Column::make('Record Count Map', 'record_count_map')->searchable()->sortable(),
            Column::make('Created At', 'created_at')->searchable()->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
