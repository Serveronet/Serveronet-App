<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiteSseEntry extends Model
{
    protected $table = 'site_sse_entries';

    protected $fillable = [
        'message',
        'event',
        'type',
        'delivered',
        'event_id',
        'site_id',
    ];

    public function saveEntry($message, $type, $event, $site_id): bool
    {
        $this->deleteDelivered();

        $data['message'] = $message;
        $data['event'] = $event;
        $data['type'] = $type;
        $data['event_id'] = Str::random(40);
        $data['site_id'] = $site_id;

        $this->fill($data);

        return $this->save();
    }

    public function deleteDelivered()
    {
        $this->where('delivered', '1')->delete();
    }
}
