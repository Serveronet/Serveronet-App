<?php

namespace App\Http\Livewire;

use App\Http\H;
use App\Models\CachedResource;
use App\Models\Site;
use App\Traits\HasReloadAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class SiteResourcesOverviewTable extends PowerGridComponent
{
    use HasReloadAction;

    public string $tableName = 'cached_resources';

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
            PowerGrid::footer()->showPerPage(20)
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

    public string $site_id;

    /**
     * PowerGrid datasource.
     *
     * @return Builder<\App\Models\CachedResource>
     */
    public function datasource(): ?Collection
    {
        $site_id = $this->site_id;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $site->most_recent_site_definition?->file_listing_json)
            return collect();

        $siteFiles = json_decode($site->most_recent_site_definition->file_listing_json);

        $crs = CachedResource::whereIn('sha256', collect($siteFiles)->pluck('sha256'))->select('sha256')->get();

        $collection = collect();

        foreach ($siteFiles as $key => $siteResource) {
            $chunks = json_decode($siteResource->chunks_json);
            $availableCount = 0;

            $row = [
                'res_id' => $siteResource->res_id,
                'file_size' => H::bytesCountToReadable($siteResource->file_size),
                'is_available' => (bool) $crs->where('sha256', $siteResource->sha256)->first(),
                'chunks' => count((array)$chunks),
                'available_chunks' => 0,
                'sha256' => $siteResource->sha256,
                'is_resource' => true,
                'ipfs_hash' => $crs->where('sha256', $siteResource->sha256)->first()?->ipfs_hash,
                'has_ipfs_hash' => (bool) $crs->where('sha256', $siteResource->sha256)->first()?->ipfs_hash,
            ];

            $collection->push($row);
            $mainRowIndex = count($collection) - 1;

            $chunksCrs = CachedResource::whereIn('sha256', collect($chunks)->pluck('sha256'))->get();

            foreach ($chunks as $key => $chunk) {
                $chunkCr = $chunksCrs->where('sha256', $chunk->sha256)->first();
                $collection->push([
                    'res_id' => ' ',
                    'file_size' => H::bytesCountToReadable($chunk->file_size),
                    'is_available' => (bool) $chunkCr,
                    'chunks' => 0,
                    'available_chunks' => 0,
                    'sha256' => $chunk->sha256,
                    'is_resource' => false,
                    'ipfs_hash' => $chunk->ipfs_hash ?? null,
                    'has_ipfs_hash' => (bool) ($chunk?->ipfs_hash ?? null),
                ]);

                if ($chunkCr)
                    $availableCount++;

                $chunkCr = null;
            }
            $rowS = $collection->get($mainRowIndex);
            $rowS['available_chunks'] = $availableCount;
            $collection = $collection->replace([$mainRowIndex => $rowS]);

            $siteResource->is_hosted = $crs->where('sha256', $siteResource->sha256)->first() ? 1 : 0;
        }

        return $collection;
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
            ->add(
                'res_id',
                fn($row) => $row->is_resource ? e($row->res_id) .
                    '<a class="text-decoration-none" target="_blank" href="' .
                    \App\Http\H::a(\App\Http\H::siteUrl($this->site_id)) . e($row->res_id)
                    . '"> ⬀ </a>' : e($row->res_id)
            )
            ->add('is_hosted')
            ->add('sha256')
            ->add('site_id')
            ->add('chunks', fn($row) => ($row->is_resource) ? e($row->available_chunks . ' / ' . $row->chunks) : '')
            ->add('file_size')
            ->add('is_available', fn($row) => $row->is_available ? '✅' : '❌')
            ->add('chunks_json')
            ->add('original_file_name')
            ->add('mime_type')
            ->add('visitor_id')
            ->add('ipfs_hash')
            ->add('has_ipfs_hash', fn($row) => $row->has_ipfs_hash ? '✅' : '❌')
        ;
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
            Column::make('Res Id', 'res_id')
                ->sortable()
                ->searchable(),

            Column::make('File Size', 'file_size')
                ->sortable(),

            Column::make('Chunks', 'chunks'),

            Column::make('File size', 'file_size'),

            Column::make('Cached', 'is_available')
                ->sortable(),

            Column::make('IPFS', 'has_ipfs_hash')
                ->sortable(),

            Column::make('Sha256', 'sha256')
                ->sortable()
                ->searchable(),

            Column::make('Ipfs Hash', 'ipfs_hash')
                ->sortable()
                ->searchable(),
        ];
    }

    /**
     * PowerGrid Filters.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }
}
