<?php

use APIToolkit\Service\APIToolkitService;
use Illuminate\Support\ServiceProvider;

class ApitoolkitServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->app->singleton(ApitoolkitService::class, function ($app) {
      return ApitoolkitService::getInstance();
    });
  }

  public function boot()
  {
  }
}
