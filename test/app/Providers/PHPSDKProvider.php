<?php
 
namespace App\Providers;
use APIToolkit\SDKs\PHPSDK;

use Illuminate\Support\ServiceProvider;
 
class PHPSDKProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Connection::class, function ($app) {
            $client = new PHPSDK();
            return $client->Client("x64dKJZFbywzmtYZh6ZsSjZN9DmWT4Seu+/q0exdpT4A9trA"); //Pass API Key
        });
    }
}