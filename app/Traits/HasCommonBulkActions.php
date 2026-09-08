<?php

namespace App\Traits;

use PowerComponents\LivewirePowerGrid\{Button};
use Illuminate\Http\Request;
use App\Services\AdminUiService;

/**
 * @property string $dataType
 */

trait HasCommonBulkActions
{
    public function refreshTable()
    {
        $this->dispatch('pg:eventRefresh-' . $this->tableName);
    }

    public function header(): array
    {
        return $this->getBulkHeader();
    }

    protected function getBulkHeader()
    {
        return [
            Button::add('refresh')
                ->slot('🔄')
                ->class('btn btn-outline-secondary')
                ->dispatch('pg:eventRefresh-' . $this->tableName, []),

            Button::add('bulk-delete')
                ->slot(__('🗑'))
                ->class('btn btn-outline-secondary')
                ->dispatch('bulkPurge', [])
        ];
    }

    protected function getListeners()
    {
        return array_merge(
            parent::getListeners(),
            [
                'bulkPurge',
            ]
        );
    }

    public function bulkPurge(): void
    {
        if (count($this->checkboxValues) === 0) return;

        $request = new Request();
        $request->merge([
            'ids' => $this->checkboxValues,
            'scope' => $this->dataType
        ]);

        (new AdminUiService())->destroyById($request);

        $this->checkboxValues = [];
    }
}
