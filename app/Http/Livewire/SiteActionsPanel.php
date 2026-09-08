<?php

namespace App\Http\Livewire;

use App\Http\Consts;
use App\Http\Controllers\SiteManagerController;
use App\Models\Site;
use Exception;
use Illuminate\Http\Request;
use Livewire\Component;

class SiteActionsPanel extends Component
{
    public $count = 0;
    public $sites = [];
    public $site = null;
    public $site_id = null;
    public $title = '';
    public $scope = '';
    public $ban_reason = '';
    public $advanced_section_visible = false;
    public $operation_in_progress = false;

    protected $listeners = ['show_action_panel' => 'loadActionPanel'];

    public function loadActionPanel($site_id)
    {
        $this->resetValidation();
        $this->site_id = $site_id;
        $site = Site::whereSiteId($this->site_id)->with('most_recent_site_definition')->first();

        if (! $site) {
            $this->site_id = null;
            $this->dispatch(
                'update_header',
                title: null,
                site_id: null,
            );
            $this->dispatch('pg:eventRefresh-sites');
            return;
        }
        $this->ban_reason = $site->ban_reason;
        $this->title = $site->most_recent_site_definition->title ?? $this->site_id;
        $this->site = $site;
        $this->dispatch(
            'update_header',
            title: $this->title,
            site_id: $this->site_id,
        );
        $this->dispatch('action-end');
    }

    function showAdvanced()
    {
        $this->advanced_section_visible = ! $this->advanced_section_visible;
    }

    function editSiteState($scope)
    {
        $this->resetValidation();
        $this->scope = $scope;

        $this->validate([
            'site_id' => Consts::siteIdValidationRule,
            'ban_reason' => ['string', 'max:255', 'nullable'],
            'scope' => ['required', 'max:1024'],
        ]);

        $request = new Request();
        $request->merge([
            'site_id' => $this->site_id,
            'scope' => $this->scope,
            'ban_reason' => $this->ban_reason,
        ]);
        $this->operation_in_progress = true;

        $rc = (new SiteManagerController())->siteStateEdit($request);
        $this->operation_in_progress = false;

        if ($rc->operation_successful) {;
        } else {
            if (isset($rc->error_message)) {
                $this->dispatch('action-failed', [
                    'error_message' => $rc->error_message ?? 'error_message',
                    'data' => e($rc->data) ?? 'data-extra_json'
                ]);
            } else {
                throw new Exception("Error Processing Request " . $rc->error_message, 1);
            }
        }

        $this->dispatch('pg:eventRefresh-sites');
        $this->loadActionPanel($this->site_id);
        $this->dispatch('action-end');
    }

    public function render()
    {
        if (! $this->site_id) {
            $this->site = null;
            return '<div></div>';
        } else {
            return view('site_admin_actions', ['site' => $this->site]);
        }
    }
}
