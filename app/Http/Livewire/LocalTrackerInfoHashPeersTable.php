<?php

namespace App\Http\Livewire;

use App\Models\LocalTrackerInfoHashPeer;
use App\Models\Site;
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

    protected ?array $siteIdByInfoHashCache = null;

    protected function siteIdByInfoHash(): array
    {
        if ($this->siteIdByInfoHashCache === null) {
            $map = [];
            foreach (Site::query()->pluck('site_id') as $siteId) {
                $map[sha1((string) $siteId)] = $siteId;
            }

            $this->siteIdByInfoHashCache = $map;
        }

        return $this->siteIdByInfoHashCache;
    }

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
            ->add('matched_site_id', fn(LocalTrackerInfoHashPeer $model) => e($this->siteIdByInfoHash()[strtolower((string) $model->info_hash)] ?? ''))
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

            Column::make('Site id', 'matched_site_id'),

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
