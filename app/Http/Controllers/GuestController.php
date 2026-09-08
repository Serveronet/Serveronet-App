<?php

namespace App\Http\Controllers;

use App\Dicts\DocsMapping;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\Peer;
use App\Models\SitePeer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Url\Url;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $requiredExtensions = ['xml', 'curl', 'zip', 'mbstring'];

        foreach ($requiredExtensions as $key => $ext_name) {
            if (! extension_loaded($ext_name)) {
                $issue = 'PHP Extension is required: '.$ext_name;
                $relavant_link = route('documentation', ['doc_tag' => DocsMapping::advanced_deployments]);

                return view('setup_issue', compact('issue', 'relavant_link'));
            }
        }

        $misconfiguredMssage = null;
        if (version_compare(PHP_VERSION, '8.3.0') < 0) {
            $issue = 'PHP Version > 8.3 is required';
            $relavant_link = route('documentation', ['doc_tag' => DocsMapping::advanced_deployments]);

            return view('setup_issue', compact('issue', 'relavant_link'));
        }

        $upload_max_filesize_ini_bytes = H::getBytesFromHumanSizeString(ini_get('upload_max_filesize'));
        if ($upload_max_filesize_ini_bytes < Consts::p2pUploadMaxFileSizeBytesNetwork) {
            $issue = 'PHP ini upload_max_filesize variable to low. Minimum 8M required. Currently: '.ini_get('upload_max_filesize');
            $relavant_link = route('documentation', ['doc_tag' => DocsMapping::advanced_deployments]);

            return view('setup_issue', compact('issue', 'relavant_link'));
        }

        $first_run_completed = H::isFirstRunCompleted();

        $accessingByIP = filter_var($request->getHost(), FILTER_VALIDATE_IP);

        if ($accessingByIP) {
            $domainHost = gethostbyaddr($request->getHost());
            $issue = 'Serveronet UI needs to be accessed via a domain instead of an IP address. Sites won\'t be accessible.';
            foreach (H::getUiAddresses() as $key => $address) {
                $host = (Url::fromString((H::getUiAddresses()[0]))->getHost());
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    $domainHost = $host;
                    break;
                }
            }

            $currentUrl = Url::fromString(request()->fullUrl());
            $proposedUrl = new Url;
            $proposedUrl = $proposedUrl->withHost($domainHost);
            $proposedUrl = $proposedUrl->withScheme($currentUrl->getScheme());
            $proposedUrl = $proposedUrl->withPort($currentUrl->getPort());

            $relavant_link = H::a($proposedUrl);

            return view('setup_issue', compact('issue', 'relavant_link'));
        }

        if (! $first_run_completed) {
            Artisan::call('route:clear');
            Artisan::call('key:generate');
            Artisan::call('config:cache');

            return redirect($request->getSchemeAndHttpHost().'/first_run_setup');
        }

        $main_loop_bootstrap_enabled = H::getSettVal(SettingIds::main_loop_bootstrap_enabled);

        return view('index', compact('misconfiguredMssage', 'main_loop_bootstrap_enabled'));
    }

    /**  techdemo-serveronet.org -> techdemo--serveronet-org */
    public static function dnsDomainToSnFormat($dnsDomain): string
    {
        /* Step 1: replace hyphens with double hyphens */
        $snFormat = str_replace('-', '--', $dnsDomain);

        /* Step 2: replace dots with single hyphens */
        $snFormat = str_replace('.', '-', $snFormat);

        return $snFormat;
    }

    /**  Accepts site_id, domain, serveronet magnet */
    public function goToSite(Request $request)
    {
        $request->validate([
            'multi_input' => 'required|string|max:4096',
        ]);
        if (Str::startsWith($request->multi_input, 'serveronet:')) {
            $uri = $request->multi_input;
            $uriExploded1 = explode(':', $uri);
            $uriExploded2 = explode('&', $uriExploded1[1]);
            $sitePeers = [];
            $site_id = null;
            foreach ($uriExploded2 as $key => $property) {
                $propertyExploded = explode('=', $property);
                if ($propertyExploded[0] == 'peer') {
                    array_push($sitePeers, urldecode($propertyExploded[1]));
                }

                if ($propertyExploded[0] == 'site_id') {
                    $site_id = urldecode($propertyExploded[1]);
                }
            }

            $request->merge(['site_id' => $site_id]);
            $request->validate([
                'site_id' => Consts::siteIdValidationRule,
            ]);

            foreach ($sitePeers as $key => $client_address) {

                if (filter_var($client_address, FILTER_VALIDATE_URL) === false) {
                    continue;
                }

                if (in_array($client_address, H::getSelfAddresses())) {
                    continue;
                }

                $sitePeer = SitePeer::where([
                    ['client_address', $client_address],
                    ['site_id', $site_id],
                ])->first();
                if (! $sitePeer) {
                    $sitePeer = new SitePeer;
                    $sitePeer->client_address = H::a($client_address);
                    $sitePeer->site_id = $site_id;
                    $sitePeer->source = 'replication';
                    $sitePeer->save();

                    $closure = function () use ($sitePeer) {
                        (new BackgroundProcessingController)->verifySitePeer($sitePeer->id);
                    };
                    H::dispatchInternalAsyncClosureWrapper($closure);

                } else {
                    $sitePeer->archived_at = null;
                    $sitePeer->save();
                }
                $peer = Peer::where('client_address', H::a($client_address))->first();
                if (! $peer) {
                    $peer = new Peer;
                    $peer->client_address = H::a($client_address);
                    $peer->save();
                    (new ClientController)->checkPeerConnectivity(peer: $peer, do_inbound_connectivity_check: false);
                }
            }
        } else {
            $site_id = $request->multi_input;

            if (Str::contains($site_id, '.')) {
                $site_id = self::dnsDomainToSnFormat($site_id);
            }

            if (! Str::contains($site_id, '-')) {
                $request->merge(['site_id' => $site_id]);
                $request->validate([
                    'site_id' => Consts::siteIdValidationRule,
                ]);
            }

            $request->merge(['site_id' => $site_id]);
        }

        return redirect(H::siteUrl($request->site_id));
    }
}
