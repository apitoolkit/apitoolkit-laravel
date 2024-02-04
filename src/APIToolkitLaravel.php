<?php

namespace APIToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

include "utils.php";
class APIToolkitLaravel
{
    public static  function observeGuzzle($request, $options)
    {
        $handlerStack = HandlerStack::create();
        $request_info = [];
        $handlerStack->push(GuzzleMiddleware::mapRequest(function ($request) use (&$request_info, $options) {
            $query = "";
            parse_str($request->getUri()->getQuery(), $query);
            $request_info = [
                "start_time" => hrtime(true),
                "method" => $request->getMethod(),
                "raw_url" => $request->getUri()->getPath() . '?' . $request->getUri()->getQuery(),
                "url_no_query" => $request->getUri()->getPath(),
                "url_path" => $options['pathPattern'] ?? $request->getUri()->getPath(),
                "headers" => $request->getHeaders(),
                "body" => $request->getBody()->getContents(),
                "query" => $query,
                "host" => $request->getUri()->getHost(),
            ];
            return $request;
        }));

        $handlerStack->push(GuzzleMiddleware::mapResponse(function ($response) use (&$request_info, $request, $options) {
            $apitoolkit = $request->apitoolkitData;
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
                'path_params' => extractPathParams($request_info["url_path"], $request_info["url_no_query"]),
                'raw_url' => $request_info["raw_url"],
                'referer' => "",
                'request_headers' => redactHeaderFields($options["redactHeaders"] ?? [], $request_info["headers"]),
                'response_headers' => redactHeaderFields($options["redactHeaders"] ?? [], $response->getHeaders()),
                'request_body' => base64_encode(redactJSONFields($options["redactRequestBody"] ?? [], $request_info["body"])),
                'response_body' => base64_encode(redactJSONFields($options["redactResponseBody"] ?? [], $respBody)),
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

    public static function reportError($error, $request)
    {
        $atError = buildError($error);
        $apitoolkit = $request->apitoolkitData;
        $client = $apitoolkit['client'];
        $client->addError($atError);
    }
}
