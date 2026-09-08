<?php

namespace App\Dicts;

class ActionTypes
{
    const retrieve_site_definition = 'retrieve_site_definition';

    /* General resource - for Site Resource and Visitor Resource as well */
    const retrieve_resource = 'retrieve_resource'; 

    const retrieve_site_peers = 'retrieve_site_peers';

    const retrieve_visitor_resource_defintion = 'retrieve_visitor_resource_defintion';

    
    const upload_site_resource = 'upload_site_resource';
    
    const upload_visitor_resource = 'upload_visitor_resource';

    const crowd_query = 'crowd_query';

    const send_targeted_p2p_message = 'send_targeted_p2p_message';

    /* For Passive/Active */
    const action_order_crowd_query = 'action_order_crowd_query';

    const action_order_retrieval = 'action_order_retrieval';
    
    const action_send_p2p_message = 'action_send_p2p_message';
}
