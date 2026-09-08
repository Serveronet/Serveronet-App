<?php

namespace App\Http\Livewire;

use Livewire\Component;

class AutoRefresh extends Component
{
    public $tableName;

    public function mount($tableName)
    {
        $this->tableName = $tableName;
    }

    public function render()
    {
        return <<<'HTML'
        <div class="ml-1">
            <br>
        <div style="display:flex;align-items:center;gap:6px;font-size:14px;">
            <input type="checkbox" id="autoReload" style="width:14px;height:14px;">
            <label for="autoReload" style="cursor:pointer;">Auto reload (3 sec)</label>
        </div>

        <script>
            let i;
            autoReload.onchange = e =>
                e.target.checked
                    ? i = setInterval(() => Livewire.dispatch('pg:eventRefresh-{{$this->tableName}}'), 3000)
                    : clearInterval(i);
        </script>
        </div>
        HTML;
    }
}
