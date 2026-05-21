<?php

namespace App\Providers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiGateway::class, fn () => new AiGateway);
    }

    public function boot(): void
    {
        //
    }
}
