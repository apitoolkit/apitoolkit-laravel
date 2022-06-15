<?php

namespace APIToolkit\Provider;

use APIToolkit\Service\APIToolkitService;
use Illuminate\Support\ServiceProvider;

class APIToolkitServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->app->singleton(ApitoolkitService::class, function ($app) {
      return ApitoolkitService(generateRandomString());
    });
  }

  public function boot()
  {
  }
}

function generateRandomString($length = 10)
{
  return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}
