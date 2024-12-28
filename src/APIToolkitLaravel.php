<?php

namespace APIToolkit;

use Apitoolkit\Common\Shared;

class APIToolkitLaravel
{
    public static  function observeGuzzle($request, $options) {
         return  Shared::observeGuzzle($request, $options);
    }
    public static function reportError($error, $request)
    {
        return  Shared::reportError($error, $request);
    }
}
