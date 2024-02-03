<?php

use JsonPath\JsonObject;
use JsonPath\InvalidJsonException;

function extractPathParams($pattern, $url)
{
    $patternSegments = explode('/', trim($pattern, '/'));
    $urlSegments = explode('/', trim($url, '/'));

    $params = array();

    foreach ($patternSegments as $key => $segment) {
        if (strpos($segment, '{') === 0 && strpos($segment, '}') === strlen($segment) - 1) {
            $paramName = trim($segment, '{}');
            if (isset($urlSegments[$key])) {
                $params[$paramName] = $urlSegments[$key];
            }
        }
    }

    return $params;
}

function rootCause($err)
{
    $cause = $err;
    while ($cause && property_exists($cause, 'cause')) {
        $cause = $cause->cause;
    }
    return $cause;
}

function buildError($err)
{
    $errType = get_class($err);
    $rootError = rootCause($err);
    $rootErrorType = get_class($rootError);

    return [
        'when' => date('c'),
        'error_type' => $errType,
        'message' => $err->getMessage(),
        'root_error_type' => $rootErrorType,
        'root_error_message' => $rootError->getMessage(),
        'stack_trace' => $err->getTraceAsString(),
    ];
}

function redactHeaderFields(array $redactKeys, array $headerFields): array
{
    array_walk($headerFields, function (&$value, $key, $redactKeys) {
        if (in_array(strtolower($key), array_map('strtolower', $redactKeys))) {
            $value = ['[CLIENT_REDACTED]'];
        }
    }, $redactKeys);
    return $headerFields;
}

// redactJSONFields accepts a list of jsonpath's to redact, and a json object to redact from, 
// and returns the final json after the redacting has been done.
function redactJSONFields(array $redactKeys, string $jsonStr): string
{
    try {
        $obj = new JsonObject($jsonStr);
    } catch (InvalidJsonException $e) {
        // For any data that isn't json, we simply return the data as is.
        return $jsonStr;
    }

    foreach ($redactKeys as $jsonPath) {
        $obj->set($jsonPath, '[CLIENT_REDACTED]');
    }
    return $obj->getJson();
}
