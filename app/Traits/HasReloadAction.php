<?php


namespace App\Traits;

use PowerComponents\LivewirePowerGrid\{Button};

trait HasReloadAction
{
    protected function getBulkHeader()
    {
        return [
            Button::add('refresh')
                ->slot('🔄')
                ->class('btn btn-outline-secondary')
                ->dispatch('pg:eventRefresh-' . $this->tableName, []),
        ];
    }
    public function header(): array
    {
        return $this->getBulkHeader();
    }
}
