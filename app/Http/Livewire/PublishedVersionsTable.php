<?php

namespace App\Http\Livewire;

use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsLabels;
use App\Dicts\PublishedVersionsTypes;
use App\Models\ServeronetVersion;
use App\Traits\HasCommonBulkActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PublishedVersionsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'serveronet_versions';

    protected $dataType = 'serveronet_versions';

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()
                ->showRecordCount(),
        ];
    }

    public ?string $with_dev;

    public function datasource(): ?Builder
    {
        $with_dev = $this->with_dev;

        if ($with_dev) {
            $channels = PublishedVersionsChannels::getConstants();
        } else {
            $channels = array_diff(
                PublishedVersionsChannels::getConstants(),
                [PublishedVersionsChannels::dev]
            );
        }

        $downloads = ServeronetVersion::query()->take(0);
        foreach ($channels as $key => $channel) {
            foreach (PublishedVersionsTypes::getConstants() as $key => $type) {
                $q = ServeronetVersion::where([
                    ['type', $type],
                    ['channel', $channel],
                ])->take(1)
                    ->orderByDesc('major')
                    ->orderByDesc('minor');
                $downloads->union($q);
            }
        }

        return $downloads
            ->orderByDesc('major')
            ->orderByDesc('minor');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('type')
            ->add('type_label', function (ServeronetVersion $model) {
                return PublishedVersionsLabels::$mapping[$model->type];
            })
            ->add('channel')
            ->add('version')
            ->add('created_at_formatted', fn (ServeronetVersion $model) => $model->created_at ?
                Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('file_name')
            ->add('sha256')
            ->add('download', function ($model) {
                $route = 'download_version_file';

                return '<a class="btn btn-outline-primary btn-sm" download href="'
                .domainRoute($route, ['file_name' => e($model->file_name), 'api_token' => 'your_admin_api_token']).'">🔽</a>';
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Type', 'type_label', 'type'),

            Column::make('Channel', 'channel')
                ->sortable()
                ->searchable(),

            Column::make('Version', 'version')
                ->sortable()
                ->searchable(),

            Column::make('Download', 'download'),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('File name', 'file_name')
                ->sortable()
                ->searchable(),

            Column::make('Sha256', 'sha256')
                ->sortable()
                ->searchable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('type')->operators(['contains']),
            Filter::inputText('channel')->operators(['contains']),
            Filter::inputText('version')->operators(['contains']),
            Filter::inputText('sha256')->operators(['contains']),
        ];
    }
}
