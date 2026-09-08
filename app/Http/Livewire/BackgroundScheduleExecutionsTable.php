<?php

namespace App\Http\Livewire;

use App\Models\BackgroundScheduleExecution;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\{RuleActions};
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Button, Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class BackgroundScheduleExecutionsTable extends PowerGridComponent
{
    public string $tableName = 'background_schedule_executions';

    /*
    |--------------------------------------------------------------------------
    |  Features Setup
    |--------------------------------------------------------------------------
    | Setup Table's general features
    |
    */
    public function setUp(): array
    {
        // $this->showCheckBox();

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
     * @return Builder<\App\Models\BackgroundScheduleExecution>
     */
    public function datasource(): Builder
    {
        return BackgroundScheduleExecution::orderByDesc('id');
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
            ->add('tag')
            ->add('tag_lower', fn($model) => strtolower(e($model->tag)))
            ->add('started_at_formatted', fn($model) => $model->started_at ?
                Carbon::parse($model->started_at)->format('d/m/Y H:i:s') : '')
            ->add('ended_at_formatted', fn($model) => $model->ended_at ?
                Carbon::parse($model->ended_at)->format('d/m/Y H:i:s') : '')
            ->add('taken_seconds')
            ->add('created_at_formatted', fn($model) => $model->created_at ?
                Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '');
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
            Column::make('Tag', 'tag')
                ->sortable()
                ->searchable(),

            Column::make('Started at', 'started_at_formatted', 'started_at')
                ->sortable(),

            Column::make('Ended at', 'ended_at_formatted', 'ended_at')
                ->sortable(),

            Column::make('Taken seconds', 'taken_seconds'),

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
        return [
            Filter::inputText('tag')->operators(['contains']),
            Filter::datetimepicker('started_at'),
            Filter::datetimepicker('ended_at'),
            Filter::datetimepicker('created_at'),
        ];
    }

    public function header(): array
    {
        return [
            Button::add('refresh')
                ->slot('🔄')
                ->class('btn btn-outline-secondary')
                ->dispatch('pg:eventRefresh-' . $this->tableName, []),
            Button::add('bulk-delete')
                ->slot(__('Bulk delete'))
                ->class('btn btn-outline-secondary')
                ->dispatch('bulkDelete', []),
        ];
    }
  

    /*
    |--------------------------------------------------------------------------
    | Actions Rules
    |--------------------------------------------------------------------------
    | Enable the method below to configure Rules for your Table and Action Buttons.
    |
    */

    /**
     * PowerGrid BackgroundScheduleExecution Action Rules.
     *
     * @return array<int, RuleActions>
     */

    /*
    public function actionRules(): array
    {
       return [];
    }
    */
    protected function getListeners(): array
    {
        return array_merge(
            parent::getListeners(),
            [
                'bulkDelete',
            ]
        );
    }
    public function bulkDelete(): void
    {
        BackgroundScheduleExecution::query()->delete();
    }
}
