<?php

namespace App\Http\Controllers;

use App\Dicts\DataTypes;
use App\Dicts\SettingIds;
use App\Dicts\SysProps;
use App\Http\Consts;
use App\Http\H;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\VisitorRecord;
use App\Services\P2pReplicationService;
use App\Services\RemotePeerService;
use App\Services\SiteConfigService;
use App\Services\SiteDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class QueryController extends Controller
{
    /**
     * P2P Query Endpoint
     *
     * Endpoint to query local or peer database
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response {
     *    "success": true,
     *    "data": [
     *      {"body":"Post body"}
     *     ]
     * }
     *
     * @bodyParam query_parameters json required Example: Json body of query parameters - {"table":"posts","where":[["title","!=","excluded_title"]]}
     *
     * @group Endpoints
     */
    public function p2pQueryEndpoint(Request $request): StreamedResponse|JsonResponse
    {
        $request->merge(['envelope_only' => true]);
        $request->merge(['response_as_result_container' => false]);

        return $this->queryEndpoint($request);
    }

    /**
     * Site API Query Endpoint (API Token)
     *
     * This API needs to be enabled before use
     * See regular Query Endpoint for details
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response {
     *    "success": true,
     *    "data": [
     *      {"body":"Post body"}
     *     ]
     * }
     *
     * @bodyParam api_token string required Example: your_visitor_api_token
     * @bodyParam query_parameters json required Example: Json body of query parameters - {"table":"posts","where":[["title","!=","excluded_title"]]}
     *
     * @group Api Token Endpoints
     */
    public function siteApiQueryEndpointToken(Request $request): StreamedResponse|JsonResponse
    {
        $request->merge(['envelope_only' => false]);
        $request->merge(['response_as_result_container' => false]);

        return $this->queryEndpoint($request);
    }

    /**
     * Site API Query Endpoint
     *
     * Endpoint to query local or peer database
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response {
     *    "success": true,
     *    "data": [
     *      {"body":"Post body"}
     *     ]
     * }
     *
     * @bodyParam query_parameters json required Example: Json body of query parameters - {"table":"posts","where":[["title","!=","excluded_title"]]}
     *
     * @group Records and Resources
     */
    public function siteApiQueryEndpoint(Request $request): StreamedResponse|JsonResponse
    {
        $request->merge(['envelope_only' => false]);
        $request->merge(['response_as_result_container' => false]);

        return $this->queryEndpoint($request);
    }

    /* Used for Site Api, P2p Api, Visitor Files queries */
    public function queryEndpoint(Request $request): StreamedResponse|JsonResponse|ResultContainer
    {
        info('queryEndpoint begin');
        $rc = new ResultContainer;
        $rc->operation_successful = false;
        try {
            $request->validate([
                'query_parameters' => ['required', 'max:'.(Consts::queryMaxSizeBytes)],
            ]);
        } catch (Throwable $th) {
            info('queryEndpoint failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        $envelopeOnly = $request->envelope_only ?? false;
        $responseAsResultContainer = $request->response_as_result_container ?? false;

        $query_id = $request->query_id;
        $remote_peer_id = $request->remote_peer_id;
        $trusted_site_peer_token = $request->trusted_site_peer_token;

        info('queryEndpoint $query_id '.$query_id.' $remote_peer_id '.$remote_peer_id);

        $count_only = $request->count_only;
        $debug = $request->debug;

        $fulfiller_id = H::getPublicSelfAddress();
        if ($request->originator_peer_id &&
        $request->originator_peer_id == H::getSettVal(SettingIds::peer_random_id)) {
            info('cyclic occured '.$query_id);
            $error_message = 'cyclic occured '.$query_id;
            if ($responseAsResultContainer) {
                $rc->error_message = $error_message;

                return $rc;
            } else {
                return $this->return_failure($error_message);
            }
        }
        $originator_peer_id = $request->originator_peer_id ?? H::getSettVal(SettingIds::peer_random_id);

        $ttl = $request->ttl ?? 3;

        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $site) {
            $error_message = 'Site is not known';
            if ($responseAsResultContainer) {
                $rc->error_message = $error_message;

                return $rc;
            } else {
                return $this->return_failure($error_message);
            }
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        if (! $siteConfig->site_Has_Database) {
            $error_message = 'Site has no database';
            if ($responseAsResultContainer) {
                $rc->error_message = $error_message;

                return $rc;
            } else {
                return $this->return_failure($error_message);
            }
        }

        $debug_data = [];

        $query_parameters = $request->query_parameters;

        if (gettype($query_parameters) === 'string') {
            $queryParameters = json_decode($query_parameters, true);
        } elseif (gettype($query_parameters) === 'array') {
            $queryParameters = $query_parameters;
        } elseif (gettype($query_parameters) === 'object') {
            $queryParameters = (array) $query_parameters;
        }
        $in_visitor_records_table = $queryParameters['in_visitor_records_table'] ?? false;

        if ($site->is_hosted) {

            $debug_data['result_source'] = 'Local db';
            if (! $in_visitor_records_table) {
                (new SiteDatabaseService)->prepareSiteDatabase($site_id);
            }

        } else {

            $debug_data['result_source'] = 'Crowd Query';
            $request->merge(['query_parameters' => $queryParameters]);
            $request->merge(['ttl' => $ttl]);
            $request->merge(['originator_peer_id' => $originator_peer_id]);

            $crowdQueryStartTs = now();

            /**
             * askPeersForQueryResult
             */
            $resultContainer = (new RemotePeerService)->askPeersForQueryResult($request);

            if (! $resultContainer->operation_successful) {
                if ($responseAsResultContainer) {
                    return $resultContainer;
                } else {
                    return $this->return_failure(message: $resultContainer->error_message, debug_data: $resultContainer->debug_data);
                }
            }

            $crowdQueryEndTs = now();
            $debug_data['Crowd Query miliseconds taken'] = $crowdQueryStartTs->diffInMilliseconds($crowdQueryEndTs);

            $debug_data['Crowd Query Debug Data'] = $resultContainer->debug_data ?? 'debug_data is empty';

            $envelopedRecords = $resultContainer->data ?? [];

            if ($in_visitor_records_table) {
                /* Visitor Table Query */
            } else {
                /* Site DB Query */
                (new SiteDatabaseService)->prepareSiteDatabase($site_id);
            }
            (new P2pReplicationService)->handleIncomingRecords(DataTypes::visitor_records, incomingJson: json_encode($envelopedRecords), forceSave: true, pfm: false);
        }

        if (! isset($queryParameters['table'])) {
            $error_message = 'Missing table property';
            if ($responseAsResultContainer) {
                $rc->error_message = $error_message;

                return $rc;
            } else {
                return $this->return_failure($error_message);
            }
        }

        $tableName = $queryParameters['table'];

        if ($in_visitor_records_table) {
            try {
                $queryBuilder = $this->buildQueryVisitorRecords($queryParameters, $site_id);
            } catch (Throwable $th) {
                $error_message = $th->getMessage();
                if ($responseAsResultContainer) {
                    $rc->error_message = $error_message;

                    return $rc;
                } else {
                    return $this->return_failure($error_message);
                }
            }

        } else {
            try {
                $queryBuilder = (new SiteDatabaseService)
                    ->buildQuerySiteDb($queryParameters, $site_id, $tableName);
            } catch (Throwable $th) {
                $error_message = $th->getMessage();
                if ($responseAsResultContainer) {
                    $rc->error_message = $error_message;

                    return $rc;
                } else {
                    return $this->return_failure($error_message);
                }
            }
        }

        $limit = $queryParameters['limit'] ?? 1_000_000;

        if ($envelopeOnly) {
            $queryBuilder->select(SysProps::sys_props_envelope);
        }

        $sql = $queryBuilder->toSql();

        // $queryBuilder->ddRawSql();

        try {
            $recordsCount = min($queryBuilder->count(), $limit);
        } catch (Throwable $th) {
            $error_message = $th->getMessage();
            if ($responseAsResultContainer) {
                $rc->error_message = $error_message;

                return $rc;
            } else {
                return $this->return_failure($error_message);
            }
        }

        if ($count_only && $responseAsResultContainer) {
            info('$count_only && $responseAsResultContainer');
            $rc = new ResultContainer;
            $rc->operation_successful = true;
            $rc->data = $recordsCount;

            return $rc;
        }

        if ($count_only && ! $responseAsResultContainer) {
            return $this->return_success(['records_count' => $recordsCount]);
        }

        if ($responseAsResultContainer) {
            $queryResult = $queryBuilder->get();
            $rc = new ResultContainer;
            $rc->operation_successful = true;
            $rc->data = $queryResult;

            return $rc;
        }

        info('queryEndpoint recordsCount '.$recordsCount);

        return $this->streamChunkedJson(
            $queryBuilder, $sql, $query_id, $debug_data, $fulfiller_id, $remote_peer_id,
            $recordsCount, $trusted_site_peer_token, $debug
        );

    }

    public function buildQueryVisitorRecords($queryParameters, $site_id)
    {
        $queryBuilder = VisitorRecord::query()->where('site_id', $site_id);
        if (isset($queryParameters['where'])) {
            foreach ($queryParameters['where'] as $key => $value) {
                $queryBuilder->where($value[0], $value[1], $value[2]);
            }
        }
        $queryBuilder->select(VisitorRecord::$publicProperties)
            ->orderByDesc('entity_updated')->limit(1);

        return $queryBuilder;
    }

    public function handleCrowdQueryOrder(Request $request): string
    {
        info('handleCrowdQueryOrder');
        $requestConfigSet = H::unwrapRequestConfigSet();
        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return 'Outdated or spoofed internal request';
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        info('handleCrowdQueryOrder: '.json_encode($requestConfigSet));

        $address = $requestConfigSet['address'] ?? '';
        if (empty($address)) {
            info('handleCrowdQueryOrder Address empty');

            return 'handleCrowdQueryOrder Address empty';
        }
        $client_address = $requestConfigSet['client_address'];
        $site_id = $requestConfigSet['site_id'];
        $query_parameters = $requestConfigSet['query_parameters'];
        $query_id = $requestConfigSet['query_id'];
        $ttl = $requestConfigSet['ttl'];
        $originator_peer_id = $requestConfigSet['originator_peer_id'];
        $remote_peer_id = $requestConfigSet['remote_peer_id'];
        $trusted_site_peer_token = $requestConfigSet['trusted_site_peer_token'] ?? null;
        $originator_peer_id = $requestConfigSet['originator_peer_id'];
        try {
            $url = $address;
            $client = H::setupClient(H::isTorAddress($address));

            $form_params = [
                'query_parameters' => $query_parameters,
                'site_id' => $site_id,
                'query_id' => $query_id,
                'ttl' => $ttl,
                'originator_peer_id' => $originator_peer_id,
                'remote_peer_id' => $remote_peer_id,
                'trusted_site_peer_token' => $trusted_site_peer_token ?? null,
                'debug' => true,
            ];
            $options['timeout'] = H::timeoutAdjust(H::isTorAddress($url), 10);
            $options['form_params'] = $form_params;

            // info('handleCrowdRequestPool 2 $options');
            // info(json_encode($options));

            try {
                /* Request for page 1 to get total count */
                $response = $client->post($url, $options);
                $success = false;
                try {
                    $body = (string) $response->getBody();
                    info('then body: '.Str::limit($body));
                    // info('then body full: '.$body);

                    /* Expects json with envelope fields */
                    $decoded_body = json_decode($body);
                    $success = false;

                    info('handleCrowdQueryOrder ResponseInterface '.$address);

                    if ($decoded_body) {
                        if (property_exists($decoded_body, 'success')) {
                            $success = $decoded_body->success;
                        }
                    }

                    if ($success == true) {

                        ClientController::handlePeerIsConnectable($client_address);

                        $responseObject = new stdClass;
                        $responseObject->payload = $decoded_body->data;

                        $responseObject->query_id = $decoded_body->query_id;

                        $responseObject->trusted_site_peer_token = $decoded_body->trusted_site_peer_token ?? null;
                        $responseObject->client_address = $client_address;

                        $responseObject->remote_peer_id = $decoded_body->debug_data?->remote_peer_id ?? 'Peer ID not received';
                        $responseObject->fulfiller_id = $decoded_body->debug_data?->fulfiller_id ?? 'Fullfiller not received';

                        /* Received data is not validated as only consumer validates */
                        $request = new Request;
                        $request->merge([
                            'data' => json_encode($responseObject),
                        ]);

                        (new ClientController)->handleReceiveFulfilledCrowdQuery($request);

                    }
                } catch (Throwable $e) {
                    info($e->getMessage().' '.$e->getFile().' '.$e->getLine());
                }

            } catch (Throwable $th) {
                return response('handleCrowdQueryOrder catch: '.$client_address);
            }
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
        H::endInteralRequestReporting($internalRequestId);

        return 'handleCrowdQueryOrder End';
    }

    protected function streamChunkedJson($queryBuilder, $sql, $query_id, $debug_data, $fulfiller_id, $remote_peer_id,
        $recordsCount, $trusted_site_peer_token, $debug)
    {
        return response()->stream(
            function () use ($queryBuilder, $sql, $query_id, $debug_data, $fulfiller_id, $remote_peer_id,
                $recordsCount, $trusted_site_peer_token, $debug) {

                echo '{"data":[';
                $i = 0;

                $queryBuilder->chunk(100, function ($objects) use (&$i, $recordsCount) {

                    foreach ($objects as $object) {
                        $i++;
                        if ($i <= $recordsCount) {
                            echo json_encode($object);
                        } else {
                            break;
                        }
                        if ($i <= $recordsCount - 1) {
                            echo ',';
                        }
                    }

                });

                echo '],';

                $debug_data['selected_count'] = $i;
                $debug_data['sql'] = $sql;
                $debug_data['fulfiller_Id'] = 'Query Endpoint fulfiller: '.$fulfiller_id;
                $debug_data['remote_peer_id'] = 'debug: '.$remote_peer_id;
                $debug_data = json_encode($debug_data, true);

                if ($debug) {
                    echo ' "query_id":"'.$query_id.'" ';
                }
                if ($debug) {
                    echo ', "debug_data": '.$debug_data.', "fulfiller_id": '.json_encode($fulfiller_id)
                    .' ,"remote_peer_id": '.json_encode($remote_peer_id).', "trusted_site_peer_token":'.json_encode($trusted_site_peer_token);
                }
                if ($debug) {
                    echo ',';
                }

                echo ' "sql_query":'.json_encode($sql).' ,';
                echo ' "success":true';
                echo '}';
            }, 200, ['Content-Type' => 'application/json']);
    }
}
