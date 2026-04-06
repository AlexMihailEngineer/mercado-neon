<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Typesense\Client::class, function () {
            return new \Typesense\Client([
                'nodes' => [
                    [
                        'host'     => env('TYPESENSE_HOST', 'localhost'),
                        'port'     => env('TYPESENSE_PORT', '8108'),
                        'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                    ],
                ],
                'api_key' => env('TYPESENSE_API_KEY'),
                'connection_timeout_seconds' => 2,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
