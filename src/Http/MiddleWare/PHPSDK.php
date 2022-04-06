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

    public $start;

    public $projectId;

    public $api_url = "https://app.apitoolkit.io";

    public function handle($request, Closure $next)
    {
        
        $clientmetadata = $this->getCredentials();

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

        $this->projectId = $clientmetadata["client"]["pubsub_project_id"];

        $this->start = microtime(true);

        return $next($request);

    }
    public function getCredentials() {

        $start = microtime(true);

        $config = (object) [
            "APIKey"=>env('APIToolKit_API_KEY', null),
            "RootURL"=>env('APIToolKit_ROOT_URL', null)
        ];
        if ($config->RootURL == null) {
            $url = $this->api_url;
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

        $this->start += ($end - $start);

        return [
            "APIKey"=>$config->APIKey,
            "RootURL"=>$url,
            "client"=>$clientmetadata
        ];
    }
    public function publishMessage($payload) {

        $credentials = $this->getCredentials();

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
        
        $this->end = microtime(true);

        $this->log($request, $response);
        
    }

    public function log($request, $response) {

        $since = $this->end - $this->start;
        
        print_r($since);

        $payload = (object) [
            "Duration"=>        $since,
            "Host"=>            $request->getHttpHost(),
            "Method"=>          $request->method,
            "ProjectID"=>       $this->projectId,
            "ProtoMajor"=>      1,
            "ProtoMinor"=>      1,
            "QueryParams"=>     $request->all(),
            "PathParams"=>      $request->route()->parameters(),
            "RawURL"=>          $request->fullUrl(),
            "Referrer"=>        $request->header('referrer', null),
            "RequestBody"=>     $request->getContent(),
            "RequestHeaders"=>  $request->headers,
            "ResponseBody"=>    $response->getContent(),
            "ResponseHeaders"=> $response->headers,
            "SdkType"=>         "go_bin",
            "StatusCode"=>      $response->getStatusCode(),
            "Timestamp"=>       time(),
            "URLPath"=>         $request->path(),
        ];

        $this->publishMessage($payload);
    }
}