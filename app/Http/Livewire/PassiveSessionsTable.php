<?php

namespace App\Http\Livewire;

use App\Models\PassiveSession;
use App\Traits\HasCommonBulkActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use Livewire\Attributes\On;

final class PassiveSessionsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'passive_sessions';
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
        return PassiveSession::where('last_heartbeat_at', '>', now()->subMinutes(1)->toDateTimeString());
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('passive_client_address')
            ->add('remote_ip')
            ->add('passive_token')
            ->add('should_stop', fn ($model) => e(tfyn($model->should_stop)))
            ->add('last_heartbeat_at', fn ($model) => e($model->last_heartbeat_at ? 
                Carbon::parse($model->last_heartbeat_at)->format('d/m/Y H:i:s') : ''))
            ->add('site_ids_json', fn ($model) => $this->toLines($model->site_ids_json))
            ->add('last_heartbeat_at_formatted', fn ($model) => $model->last_heartbeat_at ?? '' ? 
                Carbon::parse($model->last_heartbeat_at)->format('d/m/Y H:i:s') : '')
            ->add('last_heartbeat_at_formatted', fn ($model) =>  $model->last_heartbeat_at ?? '' ? 
                \Illuminate\Support\Carbon::create($model->last_heartbeat_at)->diffForHumans() : '')
            ;
    }

    function toLines($site_ids_json): string 
    {
        $lines = '';
        $site_ids_json = json_decode($site_ids_json, true);
        foreach ($site_ids_json as $key => $value) {
            $lines .= e($value)."<br>";
        }
        return $lines;
    }


    public function columns(): array
    {
        return [
            Column::make('Client Address', 'passive_client_address')
                ->searchable()
                ->sortable(),
            Column::make('IP', 'remote_ip')
                ->searchable()
                ->sortable(),
            Column::make('Should Stop', 'should_stop')
                ->sortable(),
                Column::action('Stop'),
            Column::make('Passive Token', 'passive_token')
                ->searchable()
                ->sortable(),
            Column::make('Last Heartbeat', 'last_heartbeat_at')
                ->sortable(),
            Column::make('Since Heartbeat', 'last_heartbeat_at_formatted')
                ->sortable(),
            Column::make('Site IDs', 'site_ids_json')
                ->searchable()
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function actions(PassiveSession $row): array
    {
        return [
            Button::add('signal_should_stop')
            ->slot('Stop')
            ->class('btn btn-outline-primary btn-sm')
            ->dispatch('signal_should_stop', ['id' => $row->id]),
        ];
    }

    #[On('signal_should_stop')] 
    public function signalShouldStop($id)
    {
        $passiveSession = PassiveSession::find($id);
        $passiveSession->should_stop = true;
        $passiveSession->save();
    }
}
