<?php

namespace App\Http\Controllers;

use App\Http\Consts;
use App\Models\Peer;
use App\Models\Site;
use Illuminate\Http\Request;

class ListingsController extends Controller
{
    public function adminSitesGrid(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        
        try {
            $request->validate([
                'site_id' => 'nullable|'.Consts::notRequiredSiteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $site_id = $request->site_id;
        
        return view('admin_sites_grid', ['site_id' => $site_id]);
    }

    public function backgroundScheduleExecutionsTable()
    {
        return view('background_schedule_executions');   
    }

    public function siteAdminActions(Request $request)
    {
        $site_id = $request->site_id;
        $request->merge(['site_id' => $site_id]);
        try {
            $request->validate([
                'site_id' => Consts::siteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $site = Site::whereSiteId($site_id)->first();
        return view('site_admin_actions', ['site' => $site]);
    }

    public function adminPeers()
    {
        return view('admin_peers');
    }

    public function replicationSessions()
    {
        return view('replication_sessions');
    }

    public function peerReplicationSessions()
    {
        return view('peer_replication_sessions');
    }

    public function internalRequests()
    {
        return view('internal_requests');
    }

    public function adminSitePeers(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        try {
            $request->validate([
                'site_id' => 'nullable|'.Consts::notRequiredSiteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $site_id = $request->site_id;

        return view('admin_site_peers', compact('site_id'));
    }

    public function adminSiteDefinitions(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        try {
            $request->validate([
                'site_id' => 'nullable|'.Consts::notRequiredSiteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $site_id = $request->site_id;

        return view('admin_site_definitions', compact('site_id'));
    }

    public function resourcesListing()
    {
        return view('resources_overview');
    }

    public function visitorsListing()
    {
        return view('visitors_overview');
    }

    public function passiveSessionsListing()
    {
        return view('passive_sessions');
    }

    public function guestGridOfSites()
    {
        return view('guest_sites');
    }
}
