<?php

namespace App\Http\Livewire;

use App\Http\H;
use App\Models\CachedResource;
use App\Models\VisitorResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class SiteVisitorResources extends PowerGridComponent
{
    public string $tableName = 'cached_resources';

    public function setUp(): array
    {
        // $this->showCheckBox();

        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage(20)
                ->showRecordCount(),
        ];
    }

    public string $site_id;

    public function datasource(): Collection
    {
        $site_id = $this->site_id;
        $crs = CachedResource::select('sha256')->get();

        $collection = collect();

        $visitorResources = VisitorResource::where([
            ['site_id', $site_id],
        ])->get();

        foreach ($visitorResources as $key => $vr) {

            $chunks = json_decode($vr->chunks_json);
            $availableCount = 0;

            $row = [
                'original_file_name' => $vr->original_file_name,
                'entity_id' => $vr->entity_id,
                'visitor_id' => $vr->visitor_id,
                'file_size' => H::bytesCountToReadable($vr->file_size),
                'is_available' => (bool) $crs->where('sha256', $vr->sha256)->first(),
                'chunks' => count((array)$chunks),
                'available_chunks' => 0,
                'sha256' => $vr->sha256,
                'is_resource' => true,
                'ipfs_hash' => $crs->where('sha256', $vr->sha256)->first()->ipfs_hash,
                'has_ipfs_hash' => (bool) $crs->where('sha256', $vr->sha256)->first()->ipfs_hash,
                'created_at' => $vr->created_at,
                'deleted_at' => $vr->deleted_at,
            ];

            $collection->push($row);
            $mainRowIndex = count($collection) - 1;

            foreach ($chunks as $key => $chunk) {
                $cr = $crs->where('sha256', $chunk->sha256)->first();

                $collection->push([
                    'original_file_name',
                    'entity_id' => $vr->entity_id,
                    'visitor_id' => $vr->visitor_id,
                    'file_size' => H::bytesCountToReadable($chunk->file_size),
                    'is_available' => (bool) $cr,
                    'chunks' => 0,
                    'available_chunks' => 0,
                    'sha256' => $chunk->sha256,
                    'is_resource' => false,
                    'ipfs_hash' => $chunk->ipfs_hash ?? null,
                    'has_ipfs_hash' => (bool) ($chunk?->ipfs_hash ?? null),
                    'created_at' => $vr->created_at,
                    'deleted_at' => $vr->deleted_at,
                ]);

                if ($cr)
                    $availableCount++;

                $cr = null;
            }
            $rowS = $collection->get($mainRowIndex);
            $rowS['available_chunks'] = $availableCount;
            $collection = $collection->replace([$mainRowIndex => $rowS]);
        }

        return $collection;
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('entity_id')
            ->add('sha256')
            ->add('site_id')
            ->add('chunks', fn($row) => ($row->is_resource) ? e($row->available_chunks . ' / ' . $row->chunks) : '')
            ->add('file_size')
            ->add('is_available', fn($row) => $row->is_available ? '✅' : '❌')
            ->add('is_resource', fn($row) => ($row->is_resource) ? 'Yes' : '')
            ->add('chunks_json')
            ->add('original_file_name')
            ->add('mime_type')
            ->add('visitor_id')
            ->add('ipfs_hash')
            ->add('has_ipfs_hash', fn($row) => $row->has_ipfs_hash ? '✅' : '❌')
            ->add('created_at_formatted', fn($model) => $model->created_at ? Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('deleted_at_formatted', fn($model) => $model->deleted_at ? Carbon::parse($model->deleted_at)->format('d/m/Y H:i:s') : '')
        ;
    }

    public function columns(): array
    {
        return [
            Column::make('Original file name', 'original_file_name')
                ->sortable()
                ->searchable(),

            Column::make('Chunks', 'chunks'),

            Column::make('File size', 'file_size'),

            Column::make('Hosted', 'is_available'),

            Column::make('IPFS', 'has_ipfs_hash')
                ->sortable(),

            Column::make('Id', 'entity_id')
                ->sortable()
                ->searchable(),

            Column::make('Resource', 'is_resource')
                ->sortable()
                ->searchable(),

            Column::make('Sha256', 'sha256')
                ->sortable()
                ->searchable(),

            Column::make('Ipfs Hash', 'ipfs_hash')
                ->sortable()
                ->searchable(),

            Column::make('Visitor id', 'visitor_id')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('Deleted at', 'deleted_at_formatted', 'deleted_at')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::boolean('is_resource'),
            Filter::inputText('entity_id')->operators(['contains']),
            Filter::inputText('sha256')->operators(['contains']),
            Filter::inputText('site_id')->operators(['contains']),
            Filter::inputText('original_file_name')->operators(['contains']),
            Filter::inputText('mime_type')->operators(['contains']),
            Filter::datetimepicker('created_at'),
            Filter::datetimepicker('deleted_at'),
        ];
    }
}
