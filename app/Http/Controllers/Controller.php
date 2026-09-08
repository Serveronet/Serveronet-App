<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use stdClass;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function return_success($data = null, $metadata = null, $debug_data = null): \Illuminate\Http\JsonResponse
    {
        $responseObject = new stdClass();
        $responseObject->success = true;
        
        if ($data) $responseObject->data = $data;
        if ($metadata) $responseObject->metadata = $metadata;
        if ($debug_data) $responseObject->debug_data = $debug_data;
        
        return response()->json($responseObject);
    }

    public function return_failure($message = null, $debug_data = null): \Illuminate\Http\JsonResponse
    {
        $responseObject = new stdClass();
        $responseObject->success = false;
        $responseObject->message = $message;
        
        if ($debug_data) $responseObject->debug_data = $debug_data;

        return response()->json($responseObject);
    }
}
