<?php

namespace APIToolkit\Service;

use APIToolkit\Exceptions\InvalidClientMetadataException;
use DateTime;
use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Support\Facades\Log;

class APIToolkitService
{
  private static $instance;
  private $apiKey;
  private $rootURL;

  /**
   * @var InvalidClientMetadataException|array
   */
  private $projectId;
  private $pubsubTopic;

  public function __construct()
  {
    $this->apiKey = env('APITOOLKIT_KEY');
    $this->rootURL = env("APITOOLKIT_ROOT_URL", "https://app.apitoolkit.io");
    $credentials = $this->getCredentials($this->rootURL, $this->apiKey);

    if (!$credentials) return;

    $this->projectId = $credentials["projectId"];
    $pubsubClient = new PubSubClient([
      "keyFile" => $credentials["client"]["pubsub_push_service_account"]
    ]);
    $this->pubsubTopic = $pubsubClient->topic($credentials["topic"]);
    Log::Debug("in apitoolkit service constructor");
  }

  public static function getInstance()
  {
    if (!self::$instance) {
      self::$instance = new self();
      Log::error("instance func");
    }
    return self::$instance;
  }

  public function credentials($url, $api_key)
  {
    $url = $url . "/api/client_metadata";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
      "Authorization: Bearer $api_key",
    );

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $response = json_decode(curl_exec($curl), 1);

    return $response;
  }

  public function getCredentials($url, $APIKey)
  {
    $clientmetadata = $this->credentials($url, $APIKey);
    if (!$clientmetadata) {
      // return new InvalidClientMetadataException("Unable to query APIToolkit for client metadata, do you have a correct APIKey? ");
      return throw new InvalidClientMetadataException("Unable to query APIToolkit for client metadata, do you have a correct APIKey? ");
    }

    return [
      "projectId" => $clientmetadata["project_id"],
      "APIKey" => $APIKey,
      "RootURL" => $url,
      "client" => $clientmetadata,
      "topic" => $clientmetadata["topic_id"]
    ];
  }

  public function publishMessage($payload)
  {
    $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $this->pubsubTopic->publish([
      "data" => $data
    ]);
  }

  public function log($request, $response)
  {
    if (!$this->pubsubTopic) return;

    $since = microtime(true) - $request->start_time;

    $query_params = [];

    foreach ($request->all() as $k => $v) {
      $query_params[$k] = [$v];
    }

    $request_headers = $request->header();
    $response_headers = $response->headers;

    $path_params = $request->route() ? $request->route()->parameters() : [];
    $path = $request->route() ?  "/" . $request->route()->uri : $request->getRequestUri();

    $timestamp = new DateTime();
    $timestamp = $timestamp->format("c");

    $host = $request->getHttpHost();

    $referer = $request->headers->get("referer");

    $payload = (object)[
      "duration" => round($since * 1000),
      "host" => $host,
      "method" => strtoupper($request->method()),
      "project_id" => $this->projectId,
      "proto_major" => 1,
      "proto_minor" => 1,
      "query_params" => $query_params,
      "path_params" => $path_params,
      "raw_url" => $request->getRequestUri(),
      "referer" => $referer ?? "",
      "request_body" => base64_encode($request->getContent()),
      "request_headers" => $request_headers,
      "response_body" => base64_encode($response->getContent()),
      "response_headers" => $response_headers->all(),
      "sdk_type" => "PhpLaravel",
      "status_code" => $response->getStatusCode(),
      "timestamp" => $timestamp,
      "url_path" => $path,
    ];

    $this->publishMessage($payload);
  }
}
