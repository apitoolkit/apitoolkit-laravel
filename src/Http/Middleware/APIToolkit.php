<?php

namespace APIToolkit\Http\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Closure;
use Illuminate\Http\Request;
use Google\Cloud\PubSub\PubSubClient;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use JsonPath\JsonObject;
use JsonPath\InvalidJsonException;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;


class APIToolkit
{
  private string $projectId;
  public bool $debug;
  public $pubsubTopic;
  private array $redactHeaders = [];
  private array $redactRequestBody = [];
  private array $redactResponseBody = [];

  public function __construct()
  {
    $apitoolkitCredentials = Cache::remember('apitoolkitInstance', 2000, function () {
      return APIToolkit::getCredentials();
    });

    $this->projectId = $apitoolkitCredentials["projectId"];
    // TODO: Is it possible to cache this pubsub client and prevent initialization on each request?
    $pubsubClient = new PubSubClient([
      "keyFile" => $apitoolkitCredentials["pubsubKeyFile"]
    ]);
    $this->pubsubTopic = $pubsubClient->topic($apitoolkitCredentials["topic"]);
    $this->debug = env('APITOOLKIT_DEBUG', false);
    if ($this->debug) {
      Log::debug('APIToolkit: Credentials loaded from server correctly');
    }
  }

  public function handle(Request $request, Closure $next)
  {
    $startTime = hrtime(true);
    $response = $next($request);
    $this->log($request, $response, $startTime);
    return $response;
  }



  public static function credentials($url, $api_key)
  {
    if (empty($url)) {
      $url = "https://app.apitoolkit.io";
    }
    $url = $url . "/api/client_metadata";

    $headers = array(
      "Authorization: Bearer $api_key",
    );

    $curlInit = curl_init($url);
    curl_setopt($curlInit, CURLOPT_URL, $url);
    curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlInit, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, false);
    $curlResponse = curl_exec($curlInit);
    $response = json_decode($curlResponse, 1);
    if ($curlResponse == false) {
      curl_error($curlInit);
    }

    curl_close($curlInit);
    return $response;
  }

  public static function getCredentials()
  {
    $APIKey = env('APITOOLKIT_KEY');
    $url = env("APITOOLKIT_ROOT_URL", "https://app.apitoolkit.io");

    $clientmetadata = self::credentials($url, $APIKey);
    if (!$clientmetadata) {
      throw new InvalidClientMetadataException("Unable to query APIToolkit for client metadata, do you have a correct APIKey? ");
    }

    return [
      "projectId" => $clientmetadata["project_id"],
      "pubsubKeyFile" => $clientmetadata["pubsub_push_service_account"],
      "topic" => $clientmetadata["topic_id"]
    ];
  }

  public function publishMessage($payload)
  {

    $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($this->debug) {
      Log::debug("APIToolkit: payload" . $data);
    }
    $this->pubsubTopic->publish([
      "data" => $data
    ]);
  }

  public function log(Request $request, $response, $startTime)
  {
    if (!$this->pubsubTopic) return;
    $payload = $this->buildPayload($request, $response, $startTime, $this->projectId);
    if ($this->debug) {
      Log::debug("APIToolkit: payload", $payload);
    }
    $this->publishMessage($payload);
  }

  // payload static method deterministically converts a request, response object, a start time and a projectId
  // into a pauload json object which APIToolkit server is able to interprete.
  public function buildPayload(Request $request, $response, $startTime, $projectId)
  {
    return [
      'duration' => round(hrtime(true) - $startTime),
      'host' => $request->getHttpHost(),
      'method' => $request->getMethod(),
      'project_id' => $projectId,
      'proto_major' => 1,
      'proto_minor' => 1,
      'query_params' => $request->query->all(),
      'path_params' =>  $request->route() ? $request->route()->parameters() : [],
      'raw_url' => $request->getRequestUri(),
      'referer' => $request->headers->get('referer', ''),
      'request_headers' => $this->redactHeaderFields($this->redactHeaders, $request->headers->all()),
      'response_headers' => $this->redactHeaderFields($this->redactHeaders, $response->headers->all()),
      'request_body' => base64_encode($this->redactJSONFields($this->redactRequestBody, $request->getContent())),
      'response_body' => base64_encode($this->redactJSONFields($this->redactResponseBody, $response->getContent())),
      'sdk_type' => 'PhpLaravel',
      'status_code' => $response->getStatusCode(),
      'timestamp' => (new \DateTime())->format('c'),
      'url_path' => $request->route() ?  "/" . $request->route()->uri : $request->getRequestUri(),
    ];
  }

  public function redactHeaderFields(array $redactKeys, array $headerFields): array
  {
    array_walk($headerFields, function (&$value, $key, $redactKeys) {
      if (in_array(strtolower($key), array_map('strtolower', $redactKeys))) {
        $value = ['[CLIENT_REDACTED]'];
      }
    }, $redactKeys);
    return $headerFields;
  }

  // redactJSONFields accepts a list of jsonpath's to redact, and a json object to redact from, 
  // and returns the final json after the redacting has been done.
  public function redactJSONFields(array $redactKeys, string $jsonStr): string
  {
    try {
      $obj = new JsonObject($jsonStr);
    } catch (InvalidJsonException $e) {
      // For any data that isn't json, we simply return the data as is.
      return $jsonStr;
    }

    foreach ($redactKeys as $jsonPath) {
      $obj->set($jsonPath, '[CLIENT_REDACTED]');
    }
    return $obj->getJson();
  }

  public static function observeGuzzle($request, $options)
  {
    $handlerStack = HandlerStack::create();
    $request_info = [];
    $query = "";
    parse_str($request->getUri()->getQuery(), $query);
    $handlerStack->push(GuzzleMiddleware::mapRequest(function ($request) use (&$request_info, $options) {
      $query = "";
      parse_str($request->getUri()->getQuery(), $query);
      $request_info = [
        "method" => $request->getMethod(),
        "start_time" => hrtime(true),
        "raw_url" => $request->getUri()->getPath() . '?' . $request->getUri()->getQuery(),
        "url_path" => $options['pathPattern'] ?? $request->getUri()->getPath(),
        "url_no_query" => $request->getUri()->getPath(),
        "query" => $query,
        "host" => $request->getUri()->getHost(),
        "headers" => $request->getHeaders(),
        "body" => $request->getBody()->getContents(),
      ];
      return $request;
    }));

    $handlerStack->push(GuzzleMiddleware::mapResponse(function ($response) use (&$request_info, $request, $options) {
      $apitoolkit = $request->getAttribute("apitoolkitData");
      $client = $apitoolkit['client'];
      $msg_id = $apitoolkit['msg_id'];
      $projectId = $apitoolkit['project_id'];
      $respBody = $response->getBody()->getContents();
      $payload = [
        'duration' => round(hrtime(true) - $request_info["start_time"]),
        'host' => $request_info["host"],
        'method' => $request_info["method"],
        'project_id' => $projectId,
        'proto_major' => 1,
        'proto_minor' => 1,
        'query_params' => $request_info["query"],
        'path_params' =>  extractPathParams($request_info["url_path"], $request_info["url_no_query"]),
        'raw_url' => $request_info["raw_url"],
        'referer' => "",
        'request_headers' => self::redactHeaderFields($options["redactHeaders"] ?? [], $request_info["headers"]),
        'response_headers' => self::redactHeaderFields($options["redactHeaders"] ?? [], $response->getHeaders()),
        'request_body' => base64_encode(self::redactJSONFields($options["redactRequestBody"] ?? [], $request_info["body"])),
        'response_body' => base64_encode(self::redactJSONFields($options["redactResponseBody"] ?? [], $respBody)),
        'errors' => [],
        'sdk_type' => 'GuzzleOutgoing',
        'parent_id' => $msg_id,
        'status_code' => $response->getStatusCode(),
        'timestamp' => (new \DateTime())->format('c'),
        'url_path' => $request_info["url_path"],
      ];
      $client->publishMessage($payload);
      $newBodyStream = \GuzzleHttp\Psr7\Utils::streamFor($respBody);

      $newResponse = new GuzzleResponse(
        $response->getStatusCode(),
        $response->getHeaders(),
        $newBodyStream,
        $response->getProtocolVersion(),
        $response->getReasonPhrase()
      );
      return $newResponse;
    }));

    $client = new Client(['handler' => $handlerStack]);
    return $client;
  }

}

class InvalidClientMetadataException extends Exception
{
}


function extractPathParams($pattern, $url){
  $patternSegments = explode('/', trim($pattern, '/'));
  $urlSegments = explode('/', trim($url, '/'));

  $params = array();

  foreach ($patternSegments as $key => $segment) {
    if (strpos($segment, '{') === 0 && strpos($segment, '}') === strlen($segment) - 1) {
      $paramName = trim($segment, '{}');
      if (isset($urlSegments[$key])) {
        $params[$paramName] = $urlSegments[$key];
      }
    }
  }
  return $params;
}