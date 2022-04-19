<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use APIToolkit\SDKs\PHPSDK;

class APIToolKitProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $client = new PHPSDK();
        $client->Client("w6NLf8Mdbi0zmtFP1qZsQzhG9DiUHNOeur/p3OlX8W8G/dPF");
        return $client;
    }
}
