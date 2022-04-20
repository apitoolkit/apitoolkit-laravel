<?php

namespace APIToolkit\Http\MiddleWare;

use APIToolkit\Service\APIToolkitService;
use Closure;
use Illuminate\Http\Request;

class APIToolkit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $apitoolkit = new APIToolkitService();

        $apitoolkit->log($request, $response);

        return $response;
    }
}