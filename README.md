# APIToolKit Laravel SDK

A PHP/Laravel SDK Wrapper for APIToolkit. It monitors incoming traffic, gathers the requests and sends the request to the apitoolkit servers.

## Installation

Run the following command to install the package:

```bash
composer require apitoolkit/apitoolkit-php-sdk
```

Set the `APITOOLKIT_API_KEY` environment variable to your API key in you `.env` file, should look like this:

```
APITOOLKIT_API_KEY=xxxxxx-xxxxx-xxxxxx-xxxxxx-xxxxxx
```

## Usage

Register the middleware in the `app/Http/Kernel.php` file under the `api` middleware group:

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    ...
    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        ...
        'api' => [
            ...
            \APIToolkit\Http\Middleware\APIToolkitAPIToolkit::class,
            ...
        ],
    ];
    ...
}
```

Alternatively, if you want to monitor specific routes, you can register the middleware, like this:

```php
    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        ...
        'apitoolkit' => \APIToolkit\Http\Middleware\APIToolkitAPIToolkit::class,
    ];
```

Then you can use the `apitoolkit` middleware in your routes:

```php
Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to your new application!'
    ]);
})->middleware('apitoolkit');
```