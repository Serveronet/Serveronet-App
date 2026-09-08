<?php

namespace App\Http\Controllers;

use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsTypes;
use App\Http\Consts;
use App\Models\ServeronetVersion;
use App\Services\PQCryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CentralController extends Controller
{
    /* On a Central Server */
    const serveronetSiteAddress = 'serveronet.org';
    const serveronetDemoClientAddress = 'client.serveronet.org';
    const techDemoSiteAddress = 'hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a';

    public function receiveNewVersionEndpoint(Request $request)
    {
        try {
            $request->validate([
                'record_json' => ['required', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
                'signature' => ['required', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $record_json = $request->record_json;
        $signature = $request->signature;

        $incommingVersion = json_decode($record_json);

        $signerCheckEnabled = false;

        $properSignerMatched = false;
        if ($signerCheckEnabled) {
            foreach (Consts::allowedVersionSignersVerificationKeysBase64 as $key => $signerVerKeyBase64) {

                $properSignerMatched = (new PQCryptoService)->isCryptoCorrectVerKey(
                    $signerVerKeyBase64,
                    $record_json,
                    $signature
                );
                if ($properSignerMatched) {
                    break;
                }
            }

            if (! $properSignerMatched) {
                return $this->return_failure('Signature verification failure');
            }
        }

        $version = new ServeronetVersion;
        $version->sha256 = $incommingVersion->sha256;
        $version->version = $incommingVersion->version;
        $version->major = $incommingVersion->major;
        $version->minor = $incommingVersion->minor;
        $version->file_name = $incommingVersion->file_name;
        $version->type = $incommingVersion->type;
        $version->channel = $incommingVersion->channel;
        $version->record_json = $record_json;
        $version->signature = $signature;

        $version->save();

        return $this->return_success($version);
    }

    public function downloads()
    {
        return view('central.downloads');
    }

    public function demos()
    {
        $demos = [
            'in_client' => [
                'protocol' => 'http://',
                'link' => self::techDemoSiteAddress.'.',
                'highlight' => self::serveronetDemoClientAddress,
                'title' => 'Subdomain',
                'description' => 'Site available as a subdomain in a Severonet Client.',
            ],

            'single_site' => [
                'protocol' => 'https://',
                'link' => 'techdemo.'.self::serveronetSiteAddress,
                'highlight' => '',
                'title' => 'Single Site',
                'description' => 'Single Serveronet site deployment, reachable throught dedicated subdomain. 
                Classic DNS and a Client configuration required.',
            ],

            'snet_url' => [
                'protocol' => 'http://',
                'link' => 'techdemo-snet.',
                'highlight' => self::serveronetDemoClientAddress,
                'title' => '-snet domain',
                'description' => 'Site reachable by a custom -snet top level domain. A redirect.',
            ],

            'dot_localhost_domain' => [
                'protocol' => 'http://',
                'link' => self::techDemoSiteAddress,
                'highlight' => '.snet.localhost:15080',
                'title' => 'snet.localhost',
                'description' => 'Site available using snet.localhost address. ⚠ This will work only with a locally installed client.',
            ],

            'dot_localhost_snet_domain' => [
                'protocol' => 'http://',
                'link' => 'techdemo-snet.',
                'highlight' => 'snet.localhost:15080',
                'title' => 'snet.localhost',
                'description' => 'Site available using snet.localhost address. ⚠ This will work only with a locally installed client.',
            ],

            'classic_dns' => [
                'protocol' => 'http://',
                'link' => 'techdemo-serveronet-org.',
                'highlight' => self::serveronetDemoClientAddress,
                'title' => 'Domain in subdirectory',
                'description' => 'Site available as a subdirectory in the Severonet Client. ⚠ Classic DNS configuration is required by the site owner.
                tech-demo-serveronet-org references to tech-demo-serveronet.org',
            ],
        ];

        return view('central.demos', compact('demos'));
    }

    public function allDownloadsListing(Request $request)
    {
        $request->merge(['with_dev' => $request->with_dev]);
        $request->validate([
            'with_dev' => ['nullable', 'boolean'],
        ]);

        return view('all_published_versions', ['with_dev' => $request->with_dev]);
    }

    public function downloadMostRecentVersion_client_update(Request $request)
    {
        return $this->downloadMostRecentVersion($request->merge(['type' => PublishedVersionsTypes::client_update]));
    }

    public function downloadMostRecentVersion_windows_client_bundle(Request $request)
    {
        return $this->downloadMostRecentVersion($request->merge(['type' => PublishedVersionsTypes::windows_client_bundle]));
    }

    public function downloadMostRecentVersion_linux_and_mac_client_bundle(Request $request)
    {
        return $this->downloadMostRecentVersion($request->merge(['type' => PublishedVersionsTypes::linux_and_mac_client_bundle]));
    }

    public function downloadMostRecentVersion_server_bundle(Request $request)
    {
        return $this->downloadMostRecentVersion($request->merge(['type' => PublishedVersionsTypes::server_bundle]));
    }

    public function downloadMostRecentVersion(Request $request)
    {
        $request->validate([
            'type' => 'required|string|max:1024',
            'channel' => 'string|max:1024',
        ]);

        $defaultPublishedVersionsChannels = PublishedVersionsChannels::prod;

        $channel = $request->channel ?? $defaultPublishedVersionsChannels;

        $type = $request->type;

        $version = ServeronetVersion::where([
            ['type', $type],
            ['channel', $channel],
        ])
            ->orderByDesc('major')
            ->orderByDesc('minor')->first();

        if (! $version) {
            $message = 'No version matching parameters present in this server.';
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        if (Storage::disk('public')->exists($version->file_name)) {
            return Storage::disk('public')->download($version->file_name, $version->file_name);
        } else {
            $message = 'File not found: '.$version->file_name;
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }
    }

    public function downloadVersionFile(Request $request)
    {
        $request->merge(['file_name' => $request->file_name]);
        $request->validate([
            'file_name' => ['required', 'string', 'max:1024'],
        ]);
        $file_name = $request->file_name;

        if (preg_match('/\.\.|\/|\\\\/', $file_name)) {
            abort(400, 'Invalid file name.');
        }

        if (Storage::disk('public')->exists($file_name)) {
            return Storage::disk('public')->download($file_name, $file_name);
        } else {
            $message = 'File not found: '.$file_name;
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }
    }

    /** Used only for external IP detection fallback during the First run */
    public function getBootstrapPeers()
    {
        $bootstrapPeers = [
            ['client_address' => 'http://client.serveronet.org/'],
        ];

        return $bootstrapPeers;
    }

    public function handleListVersions()
    {
        $mostRecentVersions = collect();
        foreach (PublishedVersionsTypes::getConstants() as $key => $type) {
            foreach (PublishedVersionsChannels::getConstants() as $key => $channel) {
                $v = ServeronetVersion::where('type', $type)->where('channel', $channel)
                    ->orderByDesc('major')
                    ->orderByDesc('minor')
                    ->select(ServeronetVersion::$publicProperties)->first();
                if ($v) {
                    $mostRecentVersions->push($v);
                }
            }
        }

        return $this->return_success($mostRecentVersions);
    }
}
