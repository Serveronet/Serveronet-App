<?php

namespace App\Dicts;

class SettingIds
{
    public const auto_hosting_free_space_threshold_mb = 'auto_hosting_free_space_threshold_mb';
    
    public const auto_hosting_resources_size_threshold_mb = 'auto_hosting_resources_size_threshold_mb';
    
    public const auto_hosting_site_size_threshold_mb = 'auto_hosting_site_size_threshold_mb';

    public const last_heartbeat_ts = 'last_heartbeat_ts';

    public const main_loop_stop_signaled = 'main_loop_stop_signaled';

    public const peer_random_id = 'peer_random_id';

    public const p2p_tor_only_mode = 'p2p_tor_only_mode';

    public const tor_address = 'tor_address';

    public const tor_port = 'tor_port';

    public const was_tor_connectable = 'was_tor_connectable';

    public const last_tor_connectivity_check_ts = 'last_tor_connectivity_check_ts';
    
    public const was_ipfs_connectable = 'was_ipfs_connectable';

    public const last_ipfs_connectivity_check_ts = 'last_ipfs_connectivity_check_ts';

    public const auto_create_developed_sites_symlink = 'auto_create_developed_sites_symlink';

    public const publish_self_as_site_peer = 'publish_self_as_site_peer'; 
    
    public const enabled_list_providers = 'enabled_list_providers';

    public const client_external_port_http = 'client_external_port_http';

    public const client_external_port_https = 'client_external_port_https';
    
    public const client_internal_port_http = 'client_internal_port_http';
    
    public const client_internal_port_https = 'client_internal_port_https';

    public const http_port_forwarded = 'http_port_forwarded';

    public const https_port_forwarded = 'https_port_forwarded';

    public const allow_add_site_to_client = 'allow_add_site_to_client';

    public const recent_external_ip = 'recent_external_ip';

    public const last_external_ip_detected_ts = 'last_external_ip_detected_ts';

    public const is_public_self_address_overridden = 'is_public_self_address_overridden';

    public const static_public_self_address = 'static_public_self_address';

    public const auto_hosting_enabled = 'auto_hosting_enabled';

    public const use_source_site_peers = 'use_source_site_peers';

    public const use_source_trackers = 'use_source_trackers';

    public const ipfs_address = 'ipfs_address';
    
    public const ipfs_read_only = 'ipfs_read_only';

    public const port_forwarding_enabled = 'port_forwarding_enabled';
    
    public const main_loop_bootstrap_enabled = 'main_loop_bootstrap_enabled';
    
    public const newer_client_version_detected = 'newer_client_version_detected';

    public const newer_bundle_version_detected = 'newer_bundle_version_detected';

    public const automatic_update_enabled = 'automatic_update_enabled';

    public const last_external_port_updated_ts = 'last_external_port_updated_ts';

    public const opennic_failure_count = 'opennic_failure_count';
    
    public const opennic_api_address_ip = 'opennic_api_address_ip';

    public const max_duration_passive_session_minutes = 'max_duration_passive_session_minutes';

    public const secret_key_internal_calls = 'secret_key_internal_calls';
    
    public const client_automatic_update_channel = 'client_automatic_update_channel';

    public const client_automatic_update_source = 'client_automatic_update_source';

    public const dev_custom_central_server_address = 'dev_custom_central_server_address';
    
    
    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);
        return $r->getConstants();
    }
}
