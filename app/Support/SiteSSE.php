<?php

namespace App\Support;

use App\Http\Controllers\SiteSSEController;
use App\Models\SiteSseEntry;

class SiteSSE
{
    protected $SiteSseEntry;

    public function __construct(SiteSseEntry $SiteSseEntry)
    {
        $this->SiteSseEntry = $SiteSseEntry;
    }

    public function notify($message, $type = 'info', $event = 'message', $site_id): bool
    {
        SiteSSEController::deleteOld();

        return $this->SiteSseEntry->saveEntry($message, $type, $event, $site_id);
    }
}
