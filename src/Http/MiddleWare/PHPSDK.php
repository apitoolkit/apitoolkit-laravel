<?php 

namespace APIToolkit\SDKs;

use Closure;
use Illuminate\Support\Facades\Http;
use Google\Cloud\PubSub\PubSubClient;
use Google\Protobuf\Timestamp;

use Exception;

//use APIToolkit\SDKS\PHPSDK;

class APIKeyInvalid extends Exception {
    public function msg() {
        //error message
        $message = 'Error on line '.$this->getLine().' in '.$this->getFile()
        .'=> '.$this->getMessage();
        return $message;
    }
}

class TopicInvalid extends Exception {
    public function msg() {
        //error message
        $message = 'Error on line '.$this->getLine().' in '.$this->getFile()
        .': '.$this->getMessage();
        return $message;
    }
}

class ClientMetaDataError extends Exception {
    public function msg() {
        //error message
        $message = 'Error on line '.$this->getLine().' in '.$this->getFile()
        .': '.$this->getMessage();
        return $message;
    }
}

class PHPSDK
{

    public $projectId;

    public function handle($request, Closure $next)
    {
        $request->start_time = microtime(true);

        $clientmetadata = $this->getCredentials($request);

        if ($clientmetadata["APIKey"] == null) {
            return new APIKeyInvalid("You haven't provided a key. Please specify a valid key 'APIToolKit_API_KEY' in your .env file");
        }
        if ($clientmetadata["RootURL"] == null) {
            $url = $this->api_url;
        }
        else {
            $url = $clientmetadata["RootURL"];
        }

        $credentials = $clientmetadata["client"]["pubsub_push_service_account"];

        $request->projectId = $clientmetadata["client"]["pubsub_project_id"];

        return $next($request);

    }
    public function getCredentials($request) {

        $start = microtime(true);

        $config = (object) [
            "APIKey"=>env('APIToolKit_API_KEY', null),
            "RootURL"=>env('APIToolKit_ROOT_URL', null)
        ];
        if ($config->RootURL == null) {
            $url = "https://app.apitoolkit.io";
        }
        else {
            $url = $config->RootURL;
        }

        $url = preg_replace("/\/{1}$/", "", $url);

        $clientmetadata = Http::withoutVerifying()->withToken($config->APIKey)
            ->get($url."/api/client_metadata");
        
        if ($clientmetadata->failed()) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }
        $clientmetadata = $clientmetadata->json();

        $end = microtime(true);

        $request->start_time += ($end - $start);

        return [
            "APIKey"=>$config->APIKey,
            "RootURL"=>$url,
            "client"=>$clientmetadata
        ];
    }
    public function publishMessage($payload, $request) {

        $credentials = $this->getCredentials($request);

        $projectId = $credentials["client"]["pubsub_project_id"];

        $client = new PubSubClient([
            "projectId"=>$projectId,
            "keyFile"=>$credentials["client"]["pubsub_push_service_account"]
        ]);

        $topic = $client->topic(env('APIToolKit_TOPIC_ID', "apitoolkit-go-client"));

        $client_ = (object) [
		    "topic"=>$topic,
            "id"=>$projectId
        ];

        $data = json_encode($payload);

        $time = time();
        $timestamp = new Timestamp();
        $timestamp->setSeconds($time);
        $timestamp->setNanos(0);

        $msg = $client_->topic->publish([
            "data" => $data,
            "publishTime"=>$timestamp
        ]);

    }
    public function terminate($request, $response) {
        
        $request->end_time = microtime(true);

        $this->log($request, $response);
        
    }

    public function log($request, $response) {

        $since = $request->end_time - $request->start_time;

        $query_params = [];

        foreach ($request->all() as $k=>$v) {
            $query_params[$k] = [$v];
        }

        $request_headers = $request->header();
        $response_headers = $response->headers;

        $path_params = $request->route()->parameters();

        $path = "/".$request->path();

        foreach ($path_params as $k=>$v) {
            $path = str_replace($v, '{'.$k.'}', $path);
        }

        $timestamp = new DateTime();
        $timestamp = $timestamp->format("c");

        $payload = (object) [
            "duration"=>        $since * 1000,
            "host"=>            $request->getHttpHost(),
            "method"=>          strtoupper($request->method()),
            "project_id"=>      $request->projectId,
            "proto_major"=>     1,
            "proto_minor"=>     1,
            "query_params"=>    $query_params,
            "path_params"=>     $path_params,
            "raw_url"=>         $request->fullUrl(),
            "referer"=>         $request->header('referer', null),
            "request_body"=>    base64_encode($request->getContent()),
            "request_headers"=> $request_headers,
            "response_body"=>   base64_encode($response->getContent()),
            "response_headers"=>$response_headers,
            "sdk_type"=>        "php_laravel",
            "status_code"=>     $response->getStatusCode(),
            "timestamp"=>       $timestamp,
            "url_path"=>        $path,
        ];

        echo json_encode($payload);

        $this->publishMessage($payload, $request);
        
    }
}