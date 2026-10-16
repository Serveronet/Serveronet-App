<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Models\ReplicationSession;
use App\Traits\HasCommonBulkActions;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class ReplicationSessionsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'replication_sessions';
    protected $dataType = DataTypes::replication_sessions;

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
        return ReplicationSession::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('session_id')
            ->add('data_type')
            ->add('last_heartbeat', fn($model) =>  $model->last_heartbeat ?? '' ?
                \Illuminate\Support\Carbon::create($model->last_heartbeat)->diffForHumans() : '')
            ->add('from')
            ->add('to')
            ->add('state')
            ->add('detailed_state')
            ->add('retries')
            ->add('site_id')
        ;
    }

    public function columns(): array
    {
        return [
            Column::make('Session ID', 'session_id')->searchable()->sortable(),
            Column::make('Data Type', 'data_type')->searchable()->sortable(),
            Column::make('Last Heartbeat', 'last_heartbeat')->searchable()->sortable(),
            Column::make('From', 'from')->searchable()->sortable(),
            Column::make('To', 'to')->searchable()->sortable(),
            Column::make('State', 'state')->searchable()->sortable(),
            Column::make('Detailed State', 'detailed_state')->searchable()->sortable(),
            Column::make('Retries', 'retries')->searchable()->sortable(),
            Column::make('Site ID', 'site_id')->searchable()->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
