<?php

namespace App\Dicts;

class SchedulesTag
{
    const Send_Pending_Messages = 'Send_Pending_Messages';
    const Update_External_Port = 'Update_External_Port';
    const Update_External_Ip = 'Update_External_Ip';
    const Check_Peers_Connectivity = 'Check_Peers_Connectivity';
    const Tracker_Announcing_Hosting = 'Tracker_Announcing_Hosting';
    const Publish_Self_As_Site_Peer = 'Publish_Self_As_Site_Peer';
    const Process_Content_Retrievals = 'Process_Content_Retrievals';
    const Delete_Site_Definitions_When_Newer_Present = 'Delete_Site_Definitions_When_Newer_Present';
    const Pull_Missing_Site_Definitions = 'Pull_Missing_Site_Definitions';
    const Schedule_Pending_Downloads = 'Schedule_Pending_Downloads';
    const Process_Pending_Downloads = 'Process_Pending_Downloads';
    const Process_Pending_Actions = 'Process_Pending_Actions';
    const Review_Overdue_Pending_Actions = 'Review_Overdue_Pending_Actions';
    const Replicate_Missed_Site_Definitions = 'Replicate_Missed_Site_Definitions';
    const Replicate_Missed_Site_Peers = 'Replicate_Missed_Site_Peers';
    const Replicate_Missed_Visitor_Records = 'Replicate_Missed_Visitor_Records';
    const Replicate_Missed_Visitor_Resources = 'Replicate_Missed_Visitor_Resources';
    const Verify_Site_Peers = 'Verify_Site_Peers';
    const Purge_P2p_Message_Body = 'Purge_P2p_Message_Body';
    const Delete_Expired_Retrievals = 'Delete_Expired_Retrievals';
    const Clear_Expired_Domains_From_Cache = 'Clear_Expired_Domains_From_Cache';
    const Retry_Failed_Originated_Messages = 'Retry_Failed_Originated_Messages';
    const Delete_Expired_Cached_Resources = 'Delete_Expired_Cached_Resources';
    const Delete_Expired_Visitor_Items = 'Delete_Expired_Visitor_Items';
    const Check_Hosted_Promotable_Site_Definitions = 'Check_Hosted_Promotable_Site_Definitions';
    const Refresh_Lists_Cache = 'Refresh_Lists_Cache';
    const Process_Site_Definitions_Pending_Distribution = 'Process_Site_Definitions_Pending_Distribution';
    const Sqlite_Database_Backup = 'Sqlite_Database_Backup';
    const Report_Peers_Connectivity = 'Report_Peers_Connectivity';
    const Check_Client_Update_Available = 'Check_Client_Update_Available';
    const Get_New_Trackers_From_NewTrackon = 'Get_New_Trackers_From_NewTrackon';
    const Update_Client = 'Update_Client';
    const Check_Budle_Update_Available = 'Check_Budle_Update_Available';
    const Test_Tor_Access = 'Test_Tor_Access';
    const Test_Ipfs = 'Test_Ipfs';
    const Schuffle_Peer_Id = 'Schuffle_Peer_Id';
    const Purge_Excessive_Records = 'Purge_Excessive_Records';
    const Update_Sites_Hosted_State = 'Update_Sites_Hosted_State';
    const Update_Statistics = 'Update_Statistics';
    const Sites_Maintenance = 'Sites_Maintenance';
    const Join_Chunks_Or_Require = 'Join_Chunks_Or_Require';
    const Reset_Non_Hosted_Sites_Database = 'Reset_Non_Hosted_Sites_Database';
    const Establish_Passive_Sessions = 'Establish_Passive_Sessions';
    const Get_Newer_Site_Definitions_For_Non_Hosted = 'Get_Newer_Site_Definitions_For_Non_Hosted';
    const Ensure_Daily_Actions_Executed = 'Ensure_Daily_Actions_Executed';
    
    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);
        return $r->getConstants();
    }
}
