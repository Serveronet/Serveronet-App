<?php

namespace App\Http\Livewire;

use Livewire\Component;

class SiteHeader extends Component
{
    public $percent = 0;
    public $site_id = '';
    public $title = '';

    protected $listeners = [
        'update_header' => 'updateHeader',
    ];

    function updateHeader($title, $site_id)
    {
        $this->title = $title;
        $this->site_id = $site_id;
    }

    public function render()
    {
        if (! $this->site_id) {
            return '<div></div>';
        } else {
            return view('site_header');
        }
    }
}
