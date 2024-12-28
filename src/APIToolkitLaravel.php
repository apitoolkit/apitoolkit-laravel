<?php

namespace APIToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use APIToolkit\Common as Common;

class APIToolkitLaravel
{
    public static  function observeGuzzle($request, $options) {
         return Common::observeGuzzle($request, $options);
    }
    public static function reportError($error, $request)
    {
        return Common::reportError($error, $request);
    }
}
