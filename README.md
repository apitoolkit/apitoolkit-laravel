# apitoolkit-php-sdk
A PHP/Laravel Wrapper for APIToolkit

## Installation and Requirements
The APIToolkit PHP SDK can be used in a Laravel project as a MiddleWare by installing it as a composer package. Currently, you need to set a .env key in your application which will hold your APIToolkit API Key. This SDK interfaces with the APIToolkit REST API and Google PubSub for logging API information.
Make sure that you have the .env key, "APIToolKit_API_KEY" as a valid APIToolkit API key in your project.

### Composer
To install the PHP SDK, simply run the command:
```bash
composer require edinyangaottoho/apitoolkit-php-sdk
```
Once the installation is done, you can make use of the namespace as a middleware in routes/route groups in Laravel application which you intend to be monitored (tracked) via APIToolkit.

## Basic Usage
This example is intended for Laravel >= 5.3 and portrays a route middleware, but can be adjusted to fit your Laravel versions as you deem fit.

Given a simple Laravel project with a route with a few path parameters, you can edit your /routes/web.php file for that route as thus:
```
<?php

    use Illuminate\Support\Facades\Route;
    use APIToolkit\SDKs\PHPSDK;
    use Illuminate\Http\Response;

    Route::get('/users/{user-id}/delete', function () {
       return Response::json([
          "status" => "error",
          "message" => "Access denied"
       ], 403);
    })->middleware(PHPSDK::class);
    
?>
```
This data is sent to your APIToolkit dashboard for management.
