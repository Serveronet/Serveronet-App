<?php

namespace App\Http\Livewire;

use App\Dicts\DataTypes;
use App\Http\H;
use App\Models\Visitor;
use App\Traits\HasCommonBulkActions;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\{Column, PowerGridComponent};
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class VisitorsTable extends PowerGridComponent
{
    use HasCommonBulkActions;

    public string $tableName = 'visitors';

    protected $dataType = DataTypes::visitors;

    /*
    |--------------------------------------------------------------------------
    |  Features Setup
    |--------------------------------------------------------------------------
    | Setup Table's general features
    |
    */
    public function setUp(): array
    {
        $this->showCheckBox();

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

    /**
     * PowerGrid datasource.
     *
     * @return Builder<\App\Models\Visitor>
     */
    public function datasource(): ?Builder
    {
        return Visitor::query();
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
            ->add('img', fn($model) => '<img class="pb-1" src="' . self::generateAvatarSVG($model->visitor_id) . '" id="avatar" style="height: 31px;" />
            <span>' . $model->visitor_id . '</span>
            <div style="background-color: #' . Str::limit(e(md5($model->visitor_id), 6, '')) . 'DD; height: 1em;">&nbsp;</div>')
            ->add('visitor_id')
            ->add('alias')
            ->add('created_at_formatted', fn($model) => $model->created_at ? Carbon::parse($model->created_at)->format('d/m/Y H:i:s') : '')
            ->add('🗑', function ($model) {
                return '<a class="btn btn-outline-primary btn-sm" href="javascript:confirmDelete(\''
                    . e($model->id) . '\', \'visitors\')" >&nbsp;🗑&nbsp;</a>';
            })
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
            Column::make('Visitor', 'img', 'visitor_id')
                ->sortable()
                ->searchable(),

            Column::make('Alias', 'alias')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make(' ', '🗑'),
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

    public static function generateAvatarSVG($username, $size = 100) 
    {
        $hash = H::stringToHash($username);
        $bgColor = H::hashToColor($hash);

        $shapeType = abs($hash) % 3;
        $center = $size / 2;
        $radius = $size / 3;

        switch ($shapeType) {
            case 0:
                $shapeSVG = "<circle cx=\"$center\" cy=\"$center\" r=\"$radius\" fill=\"white\" />";
                break;
            case 1:
                $shapeSVG = "<rect x=\"" . ($center - $radius) . "\" y=\"" . ($center - $radius) . "\" width=\"" 
                . (2 * $radius) . "\" height=\"" . (2 * $radius) . "\" fill=\"white\" />";
                break;
            case 2:
                $x1 = $center;
                $y1 = $center - $radius;
                $x2 = $center - $radius;
                $y2 = $center + $radius;
                $x3 = $center + $radius;
                $y3 = $center + $radius;
                $shapeSVG = "<polygon points=\"$x1,$y1 $x2,$y2 $x3,$y3\" fill=\"white\" />";
                break;
            default:
                $shapeSVG = "";
        }

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="$size" height="$size">
        <rect width="100%" height="100%" fill="$bgColor" />
        $shapeSVG
        </svg>
        SVG;

        $base64 = base64_encode($svg);
        return "data:image/svg+xml;base64,$base64";
    }
}
