<?php

namespace App\Http\Controllers\Auth;

use App\Http\Consts;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Http\Requests\Auth\VisitorLoginRequest;
use App\Models\Site;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthenticatedVisitorSessionController extends Controller
{
    /**
     * Display the login view.
     * 
     * @group Visitor Management
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        $site_id = $request->site_id;
        
        H::siteEndpointsCommonResolve($request, $site_id, $domain);
        
        if ($invalidResult = (new UtilsController())->validatedReturnSiteId($request, $site_id)) return $invalidResult;
        
        if ($site_id === Consts::visitorControlPanelAddress) {
            $site_title = 'Visitor Control Panel';
        } else {
            $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
            $site_title = $site->most_recent_site_definition->title ?? '';
        }
        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'), true) ?? [];

        $recentVisitors = [];

        $existingRecentVisitorsFromCookie = Visitor::whereIn('visitor_id', $recent_visitor_ids)
        ->select(array_merge(Visitor::$publicProperties, ['alias']))
        ->orderByDesc('created_at')->get();
        
        foreach ($existingRecentVisitorsFromCookie as $key => $visitor) {
            $visitor->short = H::getShortSiteID($visitor->visitor_id);
            $hash = H::stringToHash($visitor->visitor_id);
            $visitor->color = H::hashToColor($hash);
            array_push($recentVisitors, $visitor);
        }

        $recentVisitors = json_encode($recentVisitors);

        $password = H::isDevNode() ? Consts::passwordForTests : '';

        return response()->view('auth.visitor-login', compact('site_id', 'site_title', 'recentVisitors', 'password'));
    }

    /**
     * Handle an incoming authentication request.
     * 
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(VisitorLoginRequest $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
        ]);
        $site_id = $request->site_id;
        
        if ($site_id == Consts::visitorControlPanelAddress) {
            
        } else {
            H::siteEndpointsCommonResolve($request, $site_id, $domain);
            
            if ($invalidResult = (new UtilsController())->validatedReturnSiteId($request, $site_id)) return $invalidResult;
        }

        $request->authenticate();

        $request->session()->regenerate();

        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'));

        $recent_visitor_ids = collect($recent_visitor_ids);

        $recent_visitor_ids->push($request->visitor_id);

        $recent_visitor_ids = $recent_visitor_ids->unique();

        $recent_visitor_ids = json_encode($recent_visitor_ids);
        
        $recent_visitor_ids = cookie('recent_visitor_ids', $recent_visitor_ids, 2147483647, '/', null, false, false);
        
        Cookie::queue($recent_visitor_ids);

        if (H::inSingleSiteMode($request)) {
            $intended = H::a($request->getSchemeAndHttpHost());
        } else {
            $intended = H::a(H::siteUrl($site_id));
        }
        // dd($intended);
        // dd(session()->get('url.intended'));
        return redirect()->intended(
            $intended
        );        
    }

    /**
     * Destroy an authenticated session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('visitor')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
