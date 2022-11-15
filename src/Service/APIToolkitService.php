<?php

namespace APIToolkit\Service;

use APIToolkit\Exceptions\InvalidClientMetadataException;
use DateTime;
use Google\Cloud\PubSub\PubSubClient;
use \Illuminate\Http\Request;


class APIToolkitService
{
    private static $instance;

    /**
     * @var InvalidClientMetadataException|array
     */
    private $projectId;
    private $pubsubTopic;

    private function __construct($credentials)
    {
        $this->projectId = $credentials["projectId"];
        // TODO: Is it possible to cache this pubsub client and prevent initialization on each request?
        $pubsubClient = new PubSubClient([
            "keyFile" => $credentials["pubsubKeyFile"]
        ]);
        $this->pubsubTopic = $pubsubClient->topic($credentials["topic"]);
    }

    public static function getInstance($credentials)
    {
        if (!self::$instance) {
            self::$instance = new self($credentials);
        }

        return self::$instance;
    }

    public static function credentials($url, $api_key)
    {
        $url = $url . "/api/client_metadata";

        $curlInit = curl_init($url);
        curl_setopt($curlInit, CURLOPT_URL, $url);
        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Authorization: Bearer $api_key",
        );

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
            return new InvalidClientMetadataException("Unable to query APIToolkit for client metadata, do you have a correct APIKey? ");
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

        $this->pubsubTopic->publish([
            "data" => $data
        ]);
    }

    public function log(Request $request, $response, $startTime)
    {
        if (!$this->pubsubTopic) return;

        $since = hrtime(true) - $startTime;

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
            "duration" => round($since),
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
