<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MongoDB\Client as MongoClient;

class MongoServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
     public function register()
     {
         $this->app->singleton(MongoClient::class, function () {
            return new MongoClient('mongodb://mongo');
         });
     }
}