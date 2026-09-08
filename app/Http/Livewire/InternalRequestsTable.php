<?php

namespace App\Http\Livewire;

use App\Models\InternalRequest;
use App\Traits\HasCommonBulkActions;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;

final class InternalRequestsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'internal_requests';
    protected $dataType = 'internal_requests';

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
        return InternalRequest::query()->orderByDesc('started_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('route')
            ->add('request_id')
            ->add('metadata')
            ->add('started_at_formatted', fn(InternalRequest $model) => Carbon::parse($model->started_at)->format('d/m/Y H:i:s'))
            ->add('ended_at_formatted', fn(InternalRequest $model) => $model->ended_at ?
                Carbon::parse($model->ended_at)->format('d/m/Y H:i:s') : '')
            ->add('taken_seconds')
            ->add('created_at_formatted', fn(InternalRequest $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make('Route', 'route')
                ->sortable()
                ->searchable(),

            Column::make('Request id', 'request_id')
                ->sortable()
                ->searchable(),

            Column::make('Ended at', 'ended_at_formatted', 'ended_at')
                ->sortable(),

            Column::make('Metadata', 'metadata')
                ->sortable()
                ->searchable(),

            Column::make('Started at', 'started_at_formatted', 'started_at')
                ->sortable(),

            Column::make('Taken seconds', 'taken_seconds'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datetimepicker('started_at'),
            Filter::datetimepicker('ended_at'),
            Filter::datetimepicker('created_at'),
        ];
    }
}
