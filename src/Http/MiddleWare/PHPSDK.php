<?php 

namespace APIToolkit\SDKs;

use Closure;

//use APIToolkit\SDKS\PHPSDK;

class APIKeyInvalid extends Exception {
    public function msg() {
        //error message
        $message = 'Error on line '.$this->getLine().' in '.$this->getFile()
        .': '.$this->getMessage();
        return $message;
    }
}
  
class PHPSDK
{
    public function handle($request, Closure $next, $APIKey=null)
    {
        if ($APIKey == null) {
            throw new APIKeyInvalid("API Key must be provided when associating the APIToolkit middleware to a route!");
            return false;
        }
        $this->useAPIToolkit($request, $next, $APIKey);
    }
    public function useAPIToolkit($request, $next, $APIKey) {

        return $next($request);
    }
}