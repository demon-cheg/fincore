<?php

namespace App\Providers;

use App\Contracts\Messaging\EventPublisher;
use App\Infrastructure\Messaging\RabbitMqPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            EventPublisher::class,
            RabbitMqPublisher::class,
        );
    }

    public function boot(): void
    {
        
    }
}