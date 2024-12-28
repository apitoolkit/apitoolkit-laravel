<?php

namespace APIToolkit\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Closure;
use Exception;
use Ramsey\Uuid\Uuid;

class APIToolkit
{
  public bool $debug;
  private array $redactHeaders = [];
  private array $redactRequestBody = [];
  private array $redactResponseBody = [];
  private array $errors = [];
  private ?string $serviceVersion;
  private ?string $serviceName;
  private array $tags = [];

  public function __construct()
  {
    $this->tags = array_map('trim', explode(",", env('APITOOLKIT_TAGS', "")));
    $this->serviceVersion = env('APITOOLKIT_SERVICE_VERSION', null);
    $this->serviceName = env('APITOOLKIT_SERVICE_NAME', "");
    $this->redactHeaders = env('APITOOLKIT_REDACT_HEADERS', []);
    $this->redactRequestBody = env('APITOOLKIT_REDACT_REQUEST_BODY', []);
    $this->redactResponseBody = env('APITOOLKIT_REDACT_RESPONSE_BODY', []);
    $this->captureRequestBody = env('APITOOLKIT_CAPTURE_REQUEST_BODY', false);
    $this->captureResponseBody = env('APITOOLKIT_CAPTURE_RESPONSE_BODY', false);
    $this->debug = env('APITOOLKIT_DEBUG', false);
    $this->config = [
      'redact_headers' => $this->redactHeaders,
      'redact_request_body' => $this->redactRequestBody,
      'redact_response_body' => $this->redactResponseBody,
      'serviceVersion' => $this->serviceVersion,
      'tags' => $this->tags,
      'capture_request_body' => $this->captureRequestBody,
      'capture_response_body' => $this->captureResponseBody,
      'debug' => $this->debug,
      'serviceName' => $this->serviceName,
    ];

    $this->debug = env('APITOOLKIT_DEBUG', false);
    if ($this->debug) {
      Log::debug('APIToolkit: Credentials loaded from server correctly');
    }
  }
  public function handle(Request $request, Closure $next)
  {
    $newUuid = Uuid::uuid4();
    $msg_id = $newUuid->toString();
    $request = $request->merge([
      'apitoolkitData' => [
        'msg_id' => $msg_id,
        'errors' => [],
      ]
    ]);
    $response = $next($request);
    $this->log($request, $response, $msg_id);
    return $response;
  }
  public function log(Request $request, $response, $msg_id, $span)
  {
    $payload = $this->buildPayload($request, $response, $startTime, $this->projectId, $msg_id);
    if ($this->debug) {
      Log::debug("APIToolkit: payload", $payload);
    }
    $errors = $request->get('apitoolkitData')['errors'] ?? [];
    Apitoolkit\Common\setAttributes(
      $span,
      $request->getHttpHost(),
      $response->getStatusCode(),
      $request->query->all(),
      $request->route() ? $request->route()->parameters() : [],
      $request->headers->all(),
      $response->headers->all(),
      $request->getMethod(),
      $request->getRequestUri(),
      $msg_id,
      $request->route() ? $request->route()->uri : $request->getRequestUri(),
      $request->getContent(),
      $response->getContent(),
      $errors,
      $this->config,
      'PhpLaravel',
      null
    );
  }
}

class InvalidClientMetadataException extends Exception
{
}

