<?php

namespace APIToolkit;

class APIToolkitLaravel
{
    public static  function observeGuzzle($request, $options) {
         return  Apitoolkit\Common\observeGuzzle($request, $options);
    }
    public static function reportError($error, $request)
    {
        return  Apitoolkit\Common\reportError($error, $request);
    }
}
