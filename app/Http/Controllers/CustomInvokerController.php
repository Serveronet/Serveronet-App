<?php

namespace App\Http\Controllers;

class CustomInvokerController extends Controller
{
    function importCustomCode($method_name, $params) 
    {
        if (file_exists(__DIR__ . '/custom_code.php')) {
            include __DIR__ . '/custom_code.php';
        } else {
            return;
        }

        if (function_exists($method_name))
        $method_name($params);
    }
}
