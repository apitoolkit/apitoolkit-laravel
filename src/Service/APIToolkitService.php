<?php

namespace APIToolkit\Service;

use APIToolkit\Exceptions\InvalidClientMetadataException;
use DateTime;
use Google\Cloud\PubSub\PubSubClient;

class APIToolkitService
{
    private $apiKey;

    /**
     * @var InvalidClientMetadataException|array
     */
    private $credentials;

    public function __construct()
    {
        $this->apiKey = env('APITOOLKIT_KEY');
        $this->credentials = $this->getCredentials($this->apiKey);
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

    public function getCredentials($APIKey)
    {
        $url = "https://app.apitoolkit.io";

        $clientmetadata = $this->credentials($url, $APIKey);

        if ($clientmetadata == false) {
            return new InvalidClientMetadataException("Unable to query APIToolkit for client metadata");
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
        $client = new PubSubClient([
            "keyFile" => $this->credentials["client"]["pubsub_push_service_account"]
        ]);

        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $topic = $client->topic($this->credentials["topic"]);

        $topic->publish([
            "data" => $data
        ]);
    }

    public function log($request, $response)
    {
        $since = microtime(true) - $request->start_time;

        $query_params = [];

        foreach ($request->all() as $k => $v) {
            $query_params[$k] = [$v];
        }

        $request_headers = $request->header();
        $response_headers = $response->headers;

        $path_params = $request->route()->parameters();

        $path = "/" . $request->path();

        foreach ($path_params as $k => $v) {
            $path = str_replace($v, "{" . $k . "}", $path);
        }

        $timestamp = new DateTime();
        $timestamp = $timestamp->format("c");

        $host = $request->getHttpHost();

        $referer = $request->headers->get("referer");

        $payload = (object)[
            "duration" => round($since * 1000),
            "host" => $host,
            "method" => strtoupper($request->method()),
            "project_id" => $this->credentials["projectId"],
            "proto_major" => 1,
            "proto_minor" => 1,
            "query_params" => $query_params,
            "path_params" => $path_params,
            "raw_url" => $request->getRequestUri(),
            "referer" => $referer ?? "",
            "request_body" => base64_encode($request->getContent()),
            "request_headers" => $request_headers,
            "response_body" => base64_encode($response->getContent()),
            "response_headers" => $response_headers,
            "sdk_type" => "PhpLaravel",
            "status_code" => $response->getStatusCode(),
            "timestamp" => $timestamp,
            "url_path" => $path,
        ];

        $this->publishMessage($payload);
    }
}