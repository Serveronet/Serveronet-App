<?php

namespace App\Http\Controllers;

use App\Http\H;
use App\Models\SiteSseEntry;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteSSEController extends BaseController
{
    public function streamSSE(): StreamedResponse
    {
        $site_id = request()->site_id;
        $response = new StreamedResponse;

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        /* Delete expired */
        self::deleteOld();

        $response->setCallback(function () use ($site_id) {

            if (connection_aborted()) {
                return;
            }
            $model = null;
            $clientId = $this->generateClientId();
            $models = SiteSseEntry::where([
                ['delivered', '0'],
                ['site_id', $site_id],
            ])->oldest()->take(100)->get();

            foreach ($models as $key => $value) {
                $clientModel = SiteSseEntry::query()
                    ->where('event_id', $value->event_id)
                    ->where('client', $clientId)
                    ->where('site_id', $site_id)
                    ->first();

                if (! $clientModel) {
                    $model = $value;
                    break;
                }
            }

            echo ':'.str_repeat(' ', 2048)."\n";
            echo "retry: 5000\n";

            if (! $model) {
                /* no new data to send */
                echo ": heartbeat\n\n";
            } else {
                /* check if we have notified this client */

                $data = json_encode([
                    'message' => $model->message,
                    'type' => strtolower($model->type),
                    'time' => $model->created_at,
                ]);

                echo 'id: '.$model->id."\n";
                echo 'event: '.$model->event."\n";
                echo 'data: '.$data."\n\n";

                $clientModel = new SiteSseEntry;
                $clientModel->message = $model->message;
                $clientModel->event = $model->event;
                $clientModel->event_id = $model->event_id;
                $clientModel->site_id = $model->site_id;
                $clientModel->type = $model->type;
                $clientModel->client = $clientId;
                $clientModel->delivered = '1';

                $clientModel->save();
            }

            H::forceFlush();

            sleep(config('site_sse.interval'));
        });

        return $response->send();
    }

    protected function generateClientId(): string
    {
        return md5(php_uname('n').$_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR']);
    }

    public static function deleteOld()
    {
        SiteSseEntry::where('created_at', '<=', now()->subSeconds(60))->take(1000)->delete();
    }
}
