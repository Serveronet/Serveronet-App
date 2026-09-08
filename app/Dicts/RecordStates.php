<?php

namespace App\Dicts;

class RecordStates
{
    const download_unknown = 'unknown';
    const download_scheduled = 'scheduled';
    const download_downloaded = 'downloaded';
    
    const action_pending = 'pending';
    const action_processing = 'processing';
    const action_completed = 'completed';
    const action_failed = 'failed';

    const retrieval_pending = 'pending';
    const retrieval_processing = 'processing';
    const retrieval_completed = 'completed';
    const retrieval_failed = 'failed';

    const query_pending = 'pending';
    const query_processing = 'processing';
    const query_completed = 'completed';
    const query_consumed = 'consumed';
    const query_failed = 'failed';
}
