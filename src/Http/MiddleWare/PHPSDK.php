<?php 

namespace APIToolkit\SDKs;

use Closure;
use Illuminate\Support\Facades\Log;
use Google\Cloud\PubSub\PubSubClient;
use Google\Protobuf\Timestamp;
use DateTime;

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

        if (!isset($clientmetadata["APIKey"])) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }

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

        $request->projectId = $clientmetadata["projectId"];

        return $next($request);

    }
    public function credentials($url, $api_key) {

        $url = $url."/api/client_metadata";

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
    public function getCredentials($request) {

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

        $clientmetadata = $this->credentials($url, $config->APIKey);
        
        if ($clientmetadata == false) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }

        $request->topic = $clientmetadata["topic_id"];

        return [
            "projectId"=>$clientmetadata["project_id"],
            "APIKey"=>$config->APIKey,
            "RootURL"=>$url,
            "client"=>$clientmetadata
        ];
    }
    public function publishMessage($payload, $request) {

        $credentials = $this->getCredentials($request);

        if (!isset($clientmetadata["APIKey"])) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }

        $client = new PubSubClient([
            "keyFile"=>$credentials["client"]["pubsub_push_service_account"]
        ]);

        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $project_id = $credentials["client"]["pubsub_project_id"];

        $topic = $client->topic($request->topic);
            
        $message = $topic->publish([
            "data" => $data
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
            $path = str_replace($v, "{".$k."}", $path);
        }

        $timestamp = new DateTime();
        $timestamp = $timestamp->format("c");

        $host = $request->getHttpHost();

        $referer = $request->headers->get("referer");

        $payload = (object) [
            "duration"=>        round($since * 1000),
            "host"=>            $host,
            "method"=>          strtoupper($request->method()),
            "project_id"=>      $request->projectId,
            "proto_major"=>     1,
            "proto_minor"=>     1,
            "query_params"=>    $query_params,
            "path_params"=>     $path_params,
            "raw_url"=>         $request->getRequestUri(),
            "referer"=>         ($referer == null)?"":$referer,
            "request_body"=>    base64_encode($request->getContent()),
            "request_headers"=> $request_headers,
            "response_body"=>   base64_encode($response->getContent()),
            "response_headers"=>$response_headers,
            "sdk_type"=>        "PhpLaravel",
            "status_code"=>     $response->getStatusCode(),
            "timestamp"=>       $timestamp,
            "url_path"=>        $path,
        ];
        
        //Log::info(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $this->publishMessage($payload, $request);
        
    }
}