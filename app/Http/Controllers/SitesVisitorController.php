<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\DataTypes;
use App\Http\H;
use App\Dicts\RecordStates;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Models\CachedDomain;
use App\Models\ContentRetrieval;
use App\Models\OpennicNameServer;
use App\Models\Site;
use App\Models\Visitor;
use App\Services\IPFSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cookie;
use Spatie\Url\Url;

class SitesVisitorController extends Controller
{
    public function showRetrievalPage(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        try {
            $request->validate([
                'missing' => ['string', 'max:1024'],
                'target' => ['nullable', 'string', 'max:1024'],
                'site_id' => Consts::siteIdValidationRule,
            ]);
        } catch (\Throwable $th) {
            $message = 'Request problems: '.$th->getMessage().' Site ID: '.$request->site_id; $status_code = 400;
            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $target = $request->target;
        $missing = $request->missing;
        $site_id = $request->site_id;
        $siteRoot = H::siteUrl($site_id);

        switch ($missing) {
            case DataTypes::site_definitions:
                $missing = 'Site Definition';
            break;
        }

        return view('retrieving_prequisites', compact('target', 'missing', 'siteRoot', 'site_id'));
    }

    public static function processContentRetrievals($site_id = null, $limit = 100, $pfm = false)
    {
        info('processContentRetrievals '.$site_id);
        $retrieval = ContentRetrieval::where([
            ['state', RecordStates::retrieval_pending],
        ]);

        if ($site_id)
        $retrieval = $retrieval->where('site_id', $site_id);

        $retrieval = $retrieval->first();

        if (! $retrieval || $limit < 1) {
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);
            return;
        }

        if ($retrieval->ipfs_hash && $retrieval->action_type == ActionTypes::retrieve_resource) {
            $retrieval->state = RecordStates::retrieval_processing;
            $retrieval->save();

            $retrievedFromIpfs = IPFSService::retrieveFromIpfs($retrieval->file_size, 
            $retrieval->ipfs_hash, $retrieval->sha256);
            
            if ($retrievedFromIpfs) {
                H::pfm('Retrieved from Ipfs: '.$retrieval->action_type.' '.$retrieval->retrieval_id);
                $retrieval->state = RecordStates::retrieval_completed;
                $retrieval->save();
            }
        }

        $retrieval->state = RecordStates::retrieval_processing;
        $retrieval->save();
        $ttl = 3;
        $originator_peer_id = H::getSettVal(SettingIds::peer_random_id);
        $request = new Request();
        $request->merge(['ttl' => $ttl]);
        $request->merge(['originator_peer_id' => $originator_peer_id]);
        $request->merge(['retrieval_id' => $retrieval->retrieval_id]);

        $rc = (new ClientController())->getDataFromHostingPeers($request);

        if ($rc->operation_successful) {
            H::pfm('Retrieved from Peers: '.$retrieval->action_type.' '.$retrieval->retrieval_id, pfm: $pfm);
            $retrieval->state = RecordStates::retrieval_completed;
            $retrieval->save();
        } else {
            H::pfm('Not retrieved: '.$retrieval->action_type.' Retr. ID'.$retrieval->retrieval_id, pfm: $pfm);
            $retrieval->state = RecordStates::retrieval_failed;
            $retrieval->save();
        }

        $limit--;
        self::processContentRetrievals($site_id, $limit, pfm: $pfm);
    }

    public static function getOpennicNameServers()
    {
        $url = 'https://api.opennicproject.org/geoip/?json';
        
        /* Currently: 116.203.98.109  */
        $opennicApiAddressIp = H::getSettVal(SettingIds::opennic_api_address_ip);

        $host = (Url::fromString(H::a($url)))->getHost(); 
        /* Won't work on termux */
        $ip = gethostbyname($host);
        
        if ($ip === $host)
        $ip = $opennicApiAddressIp;

        info('getOpennicNameServers '.$ip);

        $client = H::setupClient(false);
        $options = [
            'timeout' => 3,
            'curl' => [CURLOPT_RESOLVE => ['api.opennicproject.org:443:'.$ip] ]
        ];
        H::prepareOptions($options, $url);
        
        $response = $client->request('GET', $url, $options);
        $body = (string) $response->getBody();
        $list = json_decode($body);

        foreach ($list as $key => $serverAddress) {
            $ip = $serverAddress->ip;
            if (! empty($ip)) {
                $ons = OpennicNameServer::where('server_address', $ip)->first();
                if (! $ons) {
                    try {
                        (new OpennicNameServer(['server_address' => $ip]))->save();
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }
            }
        }
    }

    /**  techdemo--serveronet-org -> techdemo-serveronet.org  */
    public static function snFormatToDnsDomain($snDomain): string 
    {
        /* Step 1: replace double hyphens with a placeholder */
        $snDomain = str_replace('--', '__TEMP__', $snDomain);

        /* Step 2: replace single hyphens with dots */
        $snDomain = str_replace('-', '.', $snDomain);

        /* Step 3: restore placeholder back to single hyphen */
        $dnsDomain = str_replace('__TEMP__', '-', $snDomain);
        
        return $dnsDomain;
    }

    public static function getDnsLinkAddress($domain, $retries = null): string|bool
    {
        $dnsDomain = self::snFormatToDnsDomain($domain);
        $snetdnslinkDomain = 'snetdnslink.'.$dnsDomain;

        $records = @dns_get_record($snetdnslinkDomain, DNS_TXT);

        if ($records !== false && ! empty($records)) {
            $txtEntries = array_map(fn($r) => $r['txt'], $records);
            
            $resultArray = explode('=', $txtEntries[0]);
            $site_id = $resultArray[1];

            (new CachedDomain(['domain' => $domain, 'site_id' => $site_id]))->save();
            
            $site = Site::whereSiteId($site_id)->first();
            if ($site) {
                $site->domain = $domain;
                $site->save();
            }

            return $site_id;
        }

        if (! OpennicNameServer::exists()) {
            self::getOpennicNameServers();
            if (! OpennicNameServer::exists()) {
                return false;
            }
        }

        if (! $retries)
        $retries = 4;

        if ($retries > 1) {
            try {
                $onsAddress = OpennicNameServer::inRandomOrder()->first()->server_address;
                $r = new \NetDNS2\Resolver(['nameservers' => [$onsAddress]]);
                $result = $r->query('snetdnslink.'.$dnsDomain, 'txt');

                if (! $result->answer) {
                    return false;
                }

                $resultArray = explode('=', $result->answer[0]->text[0]);

                if ($resultArray[0] == 'snetdnslink' && $resultArray[1] !== null) {
                    $site_id = $resultArray[1];
                    (new CachedDomain(['domain' => $domain, 'site_id' => $site_id]))->save();
                    $site = Site::whereSiteId($site_id)->first();
                    if ($site) {
                        $site->domain = $domain;
                        $site->save();
                    }
                    return $site_id;
                } else {
                    return false;
                }
            } catch (\NetDNS2\Exception $e) {
                return false;
            } catch (\Throwable $th) {
                sleep(1);
                $retries--;
                self::getDnsLinkAddress($domain, $retries);
            }
        } else {
            return false;
        }
        return false;
    }

    public static function getDnsAAddressFromOpennic($domain, $retries = 3)
    {
        $olderServers = OpennicNameServer::where('created_at', '<', now()->subDays(2)->toDateTimeString())->get();
        foreach ($olderServers as $key => $server) {
            $server->delete();
        }
        if (! OpennicNameServer::exists()) {
            info('getDnsAAddressFromOpennic NE 1');
            self::getOpennicNameServers();
            if (! OpennicNameServer::exists()) {
                info('getDnsAAddressFromOpennic NE 2');
                return false;
            }
        }

        if ($retries > 0) {
            try {
                $onsAddress = OpennicNameServer::inRandomOrder()->first()->server_address;
                $r = new \NetDNS2\Resolver(['nameservers' => [$onsAddress], 'timeout' => 3]);
                $result = $r->query($domain);

                if (! $result->answer) 
                return false;
                
                $resultAddress = $result->answer[0]->address;

                if ($resultAddress !== null) {
                    return $resultAddress;
                } else {
                    return false;
                }
            } catch (\NetDNS2\Exception $e) {
                $opennicFailureCount = H::getSettVal(SettingIds::opennic_failure_count);
                $opennicFailureCount++;
                H::setSettingValue(SettingIds::opennic_failure_count, $opennicFailureCount);
                info('Net_DNS2_Exception '.$e->getMessage().' '.$opennicFailureCount);
                info('Net_DNS2_Exception '.$e->getMessage().' '.H::getSettVal(SettingIds::opennic_failure_count));
                return false;
            } catch (\Throwable $th) {
                info('Net_DNS2_Exception Throwable | $retries: '.$retries.' '.$th->getMessage());
                sleep(1);
                $retries--;
                self::getDnsAAddressFromOpennic($domain, $retries);
            }
        } else {
            return false;
        }
    }

    function getSiteIdForSnetDomains($domain): string|null|bool 
    {
        $dnsDomain = self::snFormatToDnsDomain($domain);
        $listId = Consts::snetDomainsAuthorityListId.'@'.Consts::snetDomainsAuthoritySiteAddress;
        $adminSignersRC = (new BackendController())->getListValuesFromProvider([$dnsDomain], $listId);

        if (! $adminSignersRC->operation_successful)
        return false;

        $list = $adminSignersRC->data;

        return $list->first()->site_id;
    }

    private static $requiredPhrase = 'Yes, delete this identitity';

    public function showVisitorsControlPanel(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
        ]);

        $site_id = $request->site_id;
        $visitor_id = Auth::guard('visitor')->user()->visitor_id;
        $requiredPhrase = self::$requiredPhrase;
        
        return view('visitors_control_panel', compact('visitor_id', 'site_id', 'requiredPhrase'));
    }

    public function forgetVisitor(Request $request)
    {
        $request->validate([
            'required_phrase' => ['required', Rule::in([self::$requiredPhrase])],
        ]);
        $currentVisitor = Auth::guard('visitor')->user();
        Visitor::find($currentVisitor->id)->delete();

        return redirect(domainRoute('guest_sites'))->with('status', 'Visitor account deleted');
    }

    public function toggleApiTokenVisitorBackends(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        $request->validate([
            'site_id' => Consts::siteIdWithDomainValidationRule,
        ]);

        $currentVisitor = Auth::guard('visitor')->user();
        $currentVisitor = Visitor::find($currentVisitor->id);
        
        if ($currentVisitor->api_token) {
            $currentVisitor->api_token = null;
            $status = 'Api Token was removed';
        } else {
            $token = hash('sha256', Str::random(40));
            $currentVisitor->api_token = $token;
            $status = 'Api Token was created';
        }
        $currentVisitor->save();
        
        return redirect(domainRoute('visitor_control_panel', ['site_id' => $request->site_id]))->with('status', $status);
    }

    public function removeRecentVisitorId(Request $request)
    {
        $request->validate([
            'recent_visitor_id_to_be_removed' => 'required|string|max:255'
        ]);

        $recent_visitor_id_to_be_removed = $request->recent_visitor_id_to_be_removed;
        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'));
        $recent_visitor_ids = collect($recent_visitor_ids)->diff($recent_visitor_id_to_be_removed);
        $recent_visitor_ids = json_encode($recent_visitor_ids);
        $path = '/';
        $recent_visitor_ids = cookie('recent_visitor_ids', $recent_visitor_ids, 2147483647, $path, null, false, false);
        Cookie::queue($recent_visitor_ids);

        return redirect()->back();
    }

    public function setAdultsOnlyAccessCookie()
    {
        $adultsOnlyAccessCookie = cookie('adults_only_accepted', true, 2147483647, null, null, false, false);

        return redirect()->back()->withCookie($adultsOnlyAccessCookie);
    }
}
