<?php

namespace APIToolkit\Http\Middleware;

use APIToolkit\Service\APIToolkitService;
use Illuminate\Support\Facades\Cache;
use Closure;
use Illuminate\Http\Request;

class APIToolkit
{
  private $apitoolkit;

  public function __construct()
  {
    $apitoolkitCredentials = Cache::remember('apitoolkitInstance',2000, function() {
      return APIToolkitService::getCredentials();
    });
    $this->apitoolkit = APIToolkitService::getInstance($apitoolkitCredentials);
  }

  public function handle(Request $request, Closure $next)
  {
    $startTime = hrtime(true);
    $response = $next($request);
    $this->apitoolkit->log($request, $response, $startTime);
    return $response;
  }
}
