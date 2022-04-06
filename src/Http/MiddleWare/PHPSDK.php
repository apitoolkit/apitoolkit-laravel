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

    public $client;

    public function handle($request, Closure $next)
    {
        $config = (object) [
            "APIKey"=>env('APIToolKit_API_KEY', null),
            "RootURL"=>env('APIToolKit_ROOT_URL', null)
        ];
        if ($config->APIKey == null) {
            return new APIKeyInvalid("You haven't provided a key. Please specify a valid key 'APIToolKit_API_KEY' in your .env file");
        }
        if ($config->RootURL == null) {
            $url = "https://app.apitoolkit.io";
        }
        else {
            $url = $config->RootURL;
        }

        $url = preg_replace("/\/{1}$/", "", $url);
        
        $APIKey = $config->APIKey;

        $clientmetadata = Http::withoutVerifying()->withToken($APIKey)
            ->get($url."/api/client_metadata");
        
        if ($clientmetadata->failed()) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }

        $clientmetadata = $clientmetadata->json();

        $credentials = $clientmetadata["pubsub_push_service_account"];

        $client = new PubSubClient([
            "projectId"=>$clientmetadata["pubsub_project_id"],
            "keyFile"=>$credentials
        ]);

        $topic = $client->topic(env('APIToolKit_TOPIC_ID', "apitoolkit-go-client"));

        $client_ = (object) [
            "pubsubClient"=>$client,
		    "phpReqsTopic"=>$topic,
		    "config"=>$config,
		    "metadata"=>(object) $clientmetadata
        ];

        $this->client = $client_;
        $this->start = time();

        return $next($request);

    }
    public function publishMessage($payload) {
        if ($this->client->phpReqsTopic == null) {
            return new TopicInvalid("Topic is not initialized!");
        }
        $data = json_encode($payload);
        $time = time();
        $timestamp = new Timestamp();
        $timestamp->setSeconds($time);
        $timestamp->setNanos(0);
        $msg = $this->client->phpReqsTopic->publish([
            "data" => $data,
            "publishTime"=>$timestamp
        ]);
    }
    public function terminate($request, $response) {
        
        $this->end = time();

        $this->log($request, $response);
        
    }

    public function log($request, $response) {

        $since = $this->end - $this->start;

        print_r($this->client);
        
        $payload = (object) [
            "Duration"=>        $since,
            "Host"=>            $request->getHttpHost(),
            "Method"=>          $request->method,
            "ProjectID"=>       $this->client->metadata->pubsub_project_id,
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
            "SdkType"=>         "apitoolkit-php-sdk",
            "StatusCode"=>      $response->getStatusCode(),
            "Timestamp"=>       time(),
            "URLPath"=>         $request->path(),
        ];

        $this->publishMessage($payload);
    }
}