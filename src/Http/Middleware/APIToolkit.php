<?php

namespace APIToolkit\Http\Middleware;

use APIToolkit\Service\APIToolkitService;
use Closure;
use Illuminate\Http\Request;

class APIToolkit
{
  private $apitoolkit;

  public function __construct(ApitoolkitService $apitoolkit)
  {
    $this->apitoolkit = $apitoolkit;
  }

  public function handle(Request $request, Closure $next)
  {
    $response = $next($request);
    $this->apitoolkit->log($request, $response);
    return $response;
  }
}
