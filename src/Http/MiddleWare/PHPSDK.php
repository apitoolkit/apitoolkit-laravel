<?php 

namespace APIToolkit\SDKs;

use Closure;
use Illuminate\Support\Facades\Http;
use Google\Cloud\PubSub\PubSubClient;
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
        
        print_r($clientmetadata);

        $credentials = $clientmetadata["pubsub_push_service_account"];

        $client = new PubSubClient([
            "projectId"=>$clientmetadata["pubsub_project_id"],
            "keyFile"=>$credentials
        ]);

        $topic = $client->topic(env('APIToolKit_TOPIC_ID', "apitoolkit-go-client"));

        $cl = (object) [
            "pubsubClient"=>$client,
		    "phpReqsTopic"=>$topic,
		    "config"=>$config,
		    "metadata"=>$clientmetadata
        ];

        $this->client = $cl;

        $this->useAPIToolkit($request, $next, $config);

    }
    public function publishMessage($payload) {
        if ($this->client->phpReqsTopic == null) {
            return new TopicInvalid("Topic is not initialized!");
        }
        $data = json_encode($payload);
        $msg = $topic->publish([
            "data" => $data,
            "publishTime"=>time()
        ]);
    }
    public function useAPIToolkit($request, $next, $config) {

        $response = $next($request);

        $start = time();
        
        $since = time() - $start;
        
        $payload = (object) [
            "Duration"=>        $since,
            "Host"=>            $request->getHttpHost(),
            "Method"=>          $request->method,
            "ProjectID"=>       $this->client->metadata->PubsubProjectId,
            "ProtoMajor"=>      1,
            "ProtoMinor"=>      1,
            "QueryParams"=>     $request->all(),
            "PathParams"=>      $request->route()->parameters(),
            "RawURL"=>          $request->fullUrl(),
            "Referrer"=>        $request->header('referrer'),
            "RequestBody"=>     $request->getContent(),
            "RequestHeaders"=>  $request->header(),
            "ResponseBody"=>    $response->getConent(),
            "ResponseHeaders"=> $response->header(),
            "SdkType"=>         "apitoolkit-php-sdk",
            "StatusCode"=>      $response->status(),
            "Timestamp"=>       time(),
            "URLPath"=>         $request->path(),
        ];

        $this->publishMessage($payload);

        return $response;
        
    }
}