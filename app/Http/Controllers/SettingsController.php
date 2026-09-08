<?php

namespace App\Http\Controllers;

use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsSources;
use App\Dicts\SettingDataTypes;
use App\Http\H;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function list_settings()
    {
        $settingsList = Setting::all();

        return view('settings', compact('settingsList'));
    }

    public function getSettings()
    {
        $settingIds = SettingIds::getConstants();

        foreach ($settingIds as $key => $value) {
            /* Ensure all settings have default */
            H::getSettVal($key);
        }
        $settingsList = Setting::select([
            'setting_id',
            'value',
            'is_advanced',
            'type',

        ])->get();

        $currentSettingsList = collect();

        foreach ($settingsList as $key => $setting) {
            if (! in_array($setting->setting_id, $settingIds)) {
                continue;
            }
            $currentSetting = new Setting();
            $currentSetting->setting_id = $setting->setting_id;
            $currentSetting->value = $setting->value;
            
            /* Set help descriptions */
            $currentSetting->desc = SettingsController::getSettingDescription($setting->setting_id);
            $currentSetting->default_value = SettingsController::getDefaultForSetting($setting->setting_id)['value'];
            $currentSetting->is_advanced = SettingsController::getDefaultForSetting($setting->setting_id)['is_advanced'];
            $currentSetting->type = SettingsController::getDefaultForSetting($setting->setting_id)['type'];
            
            if ($setting->type == 'boolean')
            $currentSetting->value = $setting->value ? true : false;

            $currentSettingsList->push($currentSetting);
        }
    
        return $currentSettingsList;
    }

    public function updateSetting(Request $request)
    {
        try {
            $request->validate([
                'setting_id' => 'required|in:' . implode(',', SettingIds::getConstants()),
                'setting_value' => 'max:101024',
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $setting_id = $request->input('setting_id');
        $setting_value = $request->input('setting_value');
        H::setSettingValue($setting_id, $setting_value);

        return $this->return_success('true');
    }

    public static function getDefaultForSetting($setting_id)
    {
        switch ($setting_id) {
            case SettingIds::auto_hosting_free_space_threshold_mb: return ['value' => 
                2000, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::auto_hosting_resources_size_threshold_mb: return ['value' => 
                5000, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::auto_hosting_site_size_threshold_mb: return ['value' => 
                100, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::last_heartbeat_ts: return ['value' => 
                now()->subMinutes(60), 'type' => SettingDataTypes::datetime, 'is_advanced' => true];
            break;
            case SettingIds::main_loop_stop_signaled: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::peer_random_id: return ['value' => 
                Str::random(12), 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::p2p_tor_only_mode: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => false];
            break;
            case SettingIds::tor_address: return ['value' => 
                '127.0.0.1', 'type' => SettingDataTypes::string, 'is_advanced' => false];
            break;
            case SettingIds::tor_port: return ['value' => 
                '9050', 'type' => SettingDataTypes::string, 'is_advanced' => false];
            break;
            case SettingIds::was_tor_connectable: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::last_tor_connectivity_check_ts: return ['value' => 
                Consts::startOfServeronet, 'type' => SettingDataTypes::datetime, 'is_advanced' => true];
            break;
            case SettingIds::was_ipfs_connectable: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::last_ipfs_connectivity_check_ts: return ['value' => 
                Consts::startOfServeronet, 'type' => SettingDataTypes::datetime, 'is_advanced' => true];
            break;
            case SettingIds::auto_create_developed_sites_symlink: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::publish_self_as_site_peer: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::enabled_list_providers: return ['value' => 
                '', 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::client_external_port_http: return ['value' => 
                8080, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::client_external_port_https: return ['value' => 
                443, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::client_internal_port_http: return ['value' => 
                15080, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::client_internal_port_https: return ['value' => 
                15443, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::http_port_forwarded: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::https_port_forwarded: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::allow_add_site_to_client: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::recent_external_ip: return ['value' => 
                '', 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::last_external_ip_detected_ts: return ['value' => 
                Consts::startOfServeronet, 'type' => SettingDataTypes::datetime, 'is_advanced' => true];
            break;
            case SettingIds::is_public_self_address_overridden: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::static_public_self_address: return ['value' => 
                '', 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::auto_hosting_enabled: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::use_source_site_peers: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::use_source_trackers: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::ipfs_address: return ['value' => 
                '127.0.0.1:5001', 'type' => SettingDataTypes::string, 'is_advanced' => false];
            break;
            case SettingIds::ipfs_read_only: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => false];
            break;
            case SettingIds::port_forwarding_enabled: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::main_loop_bootstrap_enabled: return ['value' => 
                true, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::newer_client_version_detected: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::newer_bundle_version_detected: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::automatic_update_enabled: return ['value' => 
                false, 'type' => SettingDataTypes::boolean, 'is_advanced' => true];
            break;
            case SettingIds::last_external_port_updated_ts: return ['value' => 
                Consts::startOfServeronet, 'type' => SettingDataTypes::datetime, 'is_advanced' => true];
            break;
            case SettingIds::opennic_failure_count: return ['value' => 
                0, 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::opennic_api_address_ip: return ['value' => 
                '116.203.98.109', 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::max_duration_passive_session_minutes: return ['value' => 
                '5', 'type' => SettingDataTypes::integer, 'is_advanced' => true];
            break;
            case SettingIds::secret_key_internal_calls: return ['value' => 
                Str::random(12), 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break; 
            case SettingIds::client_automatic_update_channel: return ['value' => 
                PublishedVersionsChannels::prod, 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::client_automatic_update_source: return ['value' => 
                PublishedVersionsSources::central_server, 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;
            case SettingIds::dev_custom_central_server_address: return ['value' => 
                null, 'type' => SettingDataTypes::string, 'is_advanced' => true];
            break;

            default: return ['value' => 
                '', 'type' => SettingDataTypes::string, 'is_advanced' => false];
            break;
        }
    }

    public static function getSettingDescription($setting_id)
    {
        switch ($setting_id) {
            case SettingIds::auto_hosting_free_space_threshold_mb: 
                return 'Minimum disk free space required to mark new received sites as to be hosted. MB.';
            break;
            case SettingIds::auto_hosting_resources_size_threshold_mb: 
                return 'Maximum total size of file resources to mark new received sites as to be hosted. MB.';
            break;
            case SettingIds::auto_hosting_site_size_threshold_mb: 
                return 'Maximum total size of size which will be auto hosted. MB.';
            break;
            case SettingIds::last_heartbeat_ts: 
                return 'Main loop last alive timestamp';
            break;
            case SettingIds::main_loop_stop_signaled: 
                return 'Signal to stop main loop sent state. Do not use directly.';
            break;
            case SettingIds::peer_random_id: 
                return 'Peer ID used for subsequent requests to trackers and peers. Used also to prevent circular requests. 12 Chars.';
            break;
            case SettingIds::p2p_tor_only_mode: 
                return 'All P2P communication after the First Run (bootstrap) routed through tor.';
            break;
            case SettingIds::tor_address: 
                return 'Tor address';
            break;
            case SettingIds::tor_port: 
                return 'Tor port. Defaults: 9050 (PC and Orbot), for Tor Desktop Browser: 9150';
            break;
            case SettingIds::was_tor_connectable: 
                return 'Was tor connectable';
            break;
            case SettingIds::last_tor_connectivity_check_ts: 
                return 'Last time when tor connectivity check occured.';
            break;
            case SettingIds::was_ipfs_connectable: 
                return 'Was IPFS connectable';
            break;
            case SettingIds::last_ipfs_connectivity_check_ts: 
                return 'Last time when IPFS connectivity check occured.';
            break;
            case SettingIds::auto_create_developed_sites_symlink: 
                return 'Symlink in public folder to Developed Sites will be automatically created';
            break;
            case SettingIds::enabled_list_providers: 
                return 'List Providers which are enabled on this client.';
            break;
            case SettingIds::client_external_port_http: 
                return 'Port on which port forward was succesful. Will be anounced to Trackers.';
            break;
            case SettingIds::client_external_port_https: 
                return 'Https Port on which port forward was succesful. Will be anounced to Trackers.';
            break;
            case SettingIds::allow_add_site_to_client: 
                return 'Visitor can add a Site which is not in the Client yet';
            break;
            case SettingIds::recent_external_ip: 
                return 'Cached external IP';
            break;
            case SettingIds::last_external_ip_detected_ts: 
                return 'Last time when external IP was detected.';
            break;
            case SettingIds::auto_hosting_enabled: 
                return 'Automatic hosting of new eligible sites.';
            break;
            case SettingIds::ipfs_address: 
                return 'IPFS Kubo address with port. Examples: 127.0.0.1:5001 ';
            break;
            case SettingIds::ipfs_read_only: 
                return 'IPFS Gateway in use doesn\'t allow uploading files';
            break;
            case SettingIds::main_loop_bootstrap_enabled: 
                return 'Main loop continuous execution will ensured by js script call.';
            break;
            case SettingIds::last_external_port_updated_ts: 
                return 'Last time when external Port was updated.';
            break;
            case SettingIds::opennic_failure_count: 
                return 'Opennic failure count for retries.';
            break;
            case SettingIds::opennic_api_address_ip: 
                return 'Static IP for api.opennicproject.org when regular DNS is not available.';
            break;
            case SettingIds::max_duration_passive_session_minutes: 
                return 'Max passive session duration minutes.';
            break;
            case SettingIds::http_port_forwarded: 
                return 'Http port forward was successful.';
            break;
            case SettingIds::https_port_forwarded: 
                return 'Https port forward was successful.';
            break;
            case SettingIds::is_public_self_address_overridden: 
                return 'Public self address is overridden.';
            break;
            case SettingIds::static_public_self_address: 
                return 'Static public self address.';
            break;
            case SettingIds::use_source_site_peers: 
                return 'Use source site peers.';
            break;
            case SettingIds::use_source_trackers: 
                return 'Use source trackers.';
            break;
            case SettingIds::port_forwarding_enabled: 
                return 'Port forwarding is enabled.';
            break;
            case SettingIds::newer_client_version_detected: 
                return 'Newer client version was detected.';
            break;
            case SettingIds::newer_bundle_version_detected: 
                return 'Newer bundle version was detected.';
            break;
            case SettingIds::automatic_update_enabled: 
                return 'Automatic update is enabled.';
            break;
            case SettingIds::secret_key_internal_calls: 
                return 'Secret key for internal calls.';
            break;
            case SettingIds::client_automatic_update_channel: 
                return 'Client automatic update channel.';
            break;
            case SettingIds::client_automatic_update_source: 
                return 'Client automatic update source.';
            break;
            case SettingIds::publish_self_as_site_peer: 
                return 'Publish self as a site peer.';
            break;
            case SettingIds::client_internal_port_http: 
                return 'Client internal port http.';
            break;
            case SettingIds::client_internal_port_https: 
                return 'Client internal port https.';
            break;
            case SettingIds::dev_custom_central_server_address: 
                return 'Custom Central Server Address';
            break;
            
            default:
                return '';
            break;
        }
    }
}
