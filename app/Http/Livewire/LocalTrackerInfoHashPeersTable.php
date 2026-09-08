<?php

namespace App\Http\Livewire;

use App\Models\LocalTrackerInfoHashPeer;
use App\Traits\HasCommonBulkActions;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class LocalTrackerInfoHashPeersTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'local_tracker_info_hash_peers';
    protected $dataType = 'local_tracker_info_hash_peers';

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
        return LocalTrackerInfoHashPeer::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('info_hash')
            ->add('ip')
            ->add('port')
            ->add('peer_url')
            ->add('peer_id')
            ->add('last_announce_formatted', fn($model) => e($model->last_announce ?
                Carbon::parse($model->last_announce)->format('d/m/Y H:i:s') : ''))
            ->add('created_at_formatted', fn($model) => e($model->created_at ?
                Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : ''))
            ->add('updated_at_formatted', fn($model) => e($model->updated_at ?
                Carbon::parse($model->updated_at)->format('d/m/Y H:i:s') : ''))
            ->add('a', fn($model) => e($model->archived_at ?
                Carbon::parse($model->archived_at)->format('d/m/Y H:i:s') : ''))
            ->add('last_event')
            ->add('created_at_formatted', fn(LocalTrackerInfoHashPeer $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make('Info hash', 'info_hash')
                ->sortable()
                ->searchable(),

            Column::make('Ip', 'ip')
                ->sortable()
                ->searchable(),

            Column::make('Port', 'port')
                ->sortable()
                ->searchable(),

            Column::make('Peer url', 'peer_url')
                ->sortable()
                ->searchable(),

            Column::make('Peer id', 'peer_id')
                ->sortable()
                ->searchable(),

            Column::make('Last announce', 'last_announce_formatted', 'last_announce')
                ->sortable(),

            Column::make('Last event', 'last_event')
                ->sortable()
                ->searchable(),

            Column::make('Updated at', 'updated_at_formatted', 'updated_at')
                ->sortable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('Archived at', 'archived_at_formatted', 'archived_at')
                ->sortable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
