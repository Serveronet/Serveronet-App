<?php

namespace App\Dicts;

class PeerReplicationSessionStates
{
    const created = 'created';

    const failed = 'failed';

    const execution_time_exceeded = 'execution_time_exceeded';

    const ongoing = 'ongoing';
    
    const completed = 'completed';
}
