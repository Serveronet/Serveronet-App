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
            Column::make('session_id', 'session_id')->searchable()->sortable(),
            Column::make('data_type', 'data_type')->searchable()->sortable(),
            Column::make('last_heartbeat', 'last_heartbeat')->searchable()->sortable(),
            Column::make('from', 'from')->searchable()->sortable(),
            Column::make('to', 'to')->searchable()->sortable(),
            Column::make('state', 'state')->searchable()->sortable(),
            Column::make('detailed_state', 'detailed_state')->searchable()->sortable(),
            Column::make('retries', 'retries')->searchable()->sortable(),
            Column::make('site_id', 'site_id')->searchable()->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
