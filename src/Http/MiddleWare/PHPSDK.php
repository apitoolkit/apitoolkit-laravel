<?php 

namespace APIToolkit\SDKs;

use Closure;
use Illuminate\Support\Facades\Http;
use Google\Cloud\PubSub\PubSubClient;

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
            "ProjectID"=>env('APIToolKit_PROJECT_ID', null),
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

        $clientmetadata = Http::withToken($APIKey)
            ->get($url."/api/client_metadata")->json();
        
        if ($clientmetadata->failed()) {
            return new ClientMetaDataError("Unable to query APIToolkit for client metadata");
        }

        $client = new PubSubClient([
            'projectId' => $clientmetadata["PubsubProjectId"]
        ]);

        $topic = $client->createTopic(env('APIToolKit_TOPIC_ID', "apitoolkit-go-client"));

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
        $start = time();
        
        $since = time() - $start;
        
        $payload = (object) [
            "Duration"=>        $since,
            "Host"=>            "",
            "Method"=>          "",
            "ProjectID"=>       "",
            "ProtoMajor"=>      "",
            "ProtoMinor"=>      "",
            "QueryParams"=>     "",
            "PathParams"=>      "",
            "RawURL"=>          "",
            "Referer"=>         "",
            "RequestBody"=>     "",
            "RequestHeaders"=>  "",
            "ResponseBody"=>    "",
            "ResponseHeaders"=> "",
            "SdkType"=>         "",
            "StatusCode"=>      "",
            "Timestamp"=>       time(),
            "URLPath"=>         "",
        ];

        $this->publishMessage($payload);

        return $next($request);
    }
}