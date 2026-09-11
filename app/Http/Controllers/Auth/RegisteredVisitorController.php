<?php

namespace App\Http\Controllers\Auth;

use App\Http\Consts;
use App\Http\Controllers\Controller;
use App\Http\H;
use App\Models\Visitor;
use App\Services\PQCryptoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredVisitorController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return View
     */
    public function create(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
            'target_site_id' => Consts::notRequiredSiteIdValidationRule,
        ]);

        $site_id = $request->site_id;
        if (! H::inSingleSiteMode(request())) {
            if ($site_id !== Consts::visitorControlPanelAddress) {
                return redirect(domainRoute('register', ['site_id' => Consts::visitorControlPanelAddress, 'target_site_id' => $site_id]));
            }
        }

        $keySet = (new PQCryptoService)->genKeySet();

        $visitor_id = $keySet->hashed_id;

        $site_id = $request->target_site_id ?? Consts::visitorControlPanelAddress;
        $alias = '';

        $password = H::isDevNode() ? Consts::passwordForTests : '';

        return response()->view('auth.visitor-register', compact('site_id', 'visitor_id', 'alias', 'keySet', 'password'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'target_site_id' => Consts::notRequiredSiteIdValidationRule,
        ]);

        $site_id = $request->site_id;
        if (! H::inSingleSiteMode(request())) {
            if ($site_id !== Consts::visitorControlPanelAddress) {
                throw ValidationException::withMessages(['visitor_id' => 'Registration possible only in Visitor Control Panel']);
            }
        }

        $request->validate([
            'base64_seed' => ['required', 'string', 'max:255'],
        ]);

        $keySet = (new PQCryptoService)->getKeySetFromSeed($request->base64_seed);

        $visitor_id = $keySet->hashed_id;
        $verification_key_base64 = $keySet->verification_key_base64;

        $passwordRules = Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();

        $request->validate([
            'alias' => ['string', 'nullable', 'max:255'],
            'password' => ['required', 'confirmed', $passwordRules],
        ]);

        $visitor = Visitor::where('visitor_id', $visitor_id)->first();
        $alias = $request->alias;

        if ($visitor) {

            $visitor->password = Hash::make($request->password);
            $visitor->alias = $alias;
            $visitor->save();

        } else {
            $visitor = new Visitor();
            $visitor->visitor_id = $visitor_id;
            $visitor->verification_key_base64 = $verification_key_base64;
            $visitor->encrypted_seed = encrypt(base64_decode($request->base64_seed));
            $visitor->password = Hash::make($request->password);
            $visitor->alias = $alias;

            $visitor->save();
        }

        Auth::guard('visitor')->login($visitor);

        $target_site_id = $request->target_site_id;

        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'));
        $recent_visitor_ids = collect($recent_visitor_ids);
        $recent_visitor_ids->push($visitor_id);
        $recent_visitor_ids = json_encode($recent_visitor_ids);
        $path = '/';
        $recent_visitor_ids = cookie('recent_visitor_ids', $recent_visitor_ids, 2147483647, $path, null, false, false);
        Cookie::queue($recent_visitor_ids);

        $site_id = Consts::visitorControlPanelAddress;

        $identity_createdRoute = domainRoute('identity_created', [
            'site_id' => $site_id,
            'target_site_id' => $target_site_id,
        ]);

        return redirect($identity_createdRoute);
    }

    public function identitySummary(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
            'target_site_id' => Consts::notRequiredSiteIdValidationRule,
        ]);

        $site_id = $request->site_id;
        if (! H::inSingleSiteMode(request())) {
            if ($site_id !== Consts::visitorControlPanelAddress) {
                return redirect(domainRoute('identity_summary', ['site_id' => Consts::visitorControlPanelAddress]));
            }
        }

        $target_site_id = $request->target_site_id;
        $visitor = Auth::guard('visitor')->user();

        if (Carbon::parse($visitor->created_at)->diffInMinutes(now()) > 60) {
            $message = 'Identity details are now hidden. Only client admin can recover. See the documentation for help.';
            $status_code = 403;
            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $visitor_id = $visitor->visitor_id;
        $alias = $visitor->alias;
        $base64_seed = base64_encode(decrypt($visitor->encrypted_seed));

        return view('identity_created', compact('site_id', 'target_site_id', 'visitor_id', 'base64_seed', 'alias'));
    }

    public function downloadIdentity(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
        ]);

        $site_id = $request->site_id;
        if (! H::inSingleSiteMode(request())) {
            if ($site_id !== Consts::visitorControlPanelAddress) {
                return redirect(domainRoute('visitor_control_panel', ['site_id' => Consts::visitorControlPanelAddress]));
            }
        }

        $visitor = Auth::guard('visitor')->user();

        if (Carbon::parse($visitor->created_at)->diffInMinutes(now()) > 60) {
            $message = 'Identity details are now hidden. Only client admin can recover. See the documentation for help.';
            $status_code = 403;
            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $contents = collect([
            'visitor_id' => $visitor->visitor_id,
            'alias' => $visitor->alias ?? '',
            'base64_seed' => base64_encode(decrypt($visitor->encrypted_seed)),
        ]);

        $filename = 'Serveronet_Identity_'.Str::replace(' ', '_', (string) $visitor->alias)
        .'_'.H::getShortSiteID($visitor->visitor_id, true).'.json';

        $content_json = json_encode($contents, JSON_PRETTY_PRINT);

        return response()->streamDownload(function () use ($content_json) {
            echo $content_json;
        }, $filename);
    }
}
