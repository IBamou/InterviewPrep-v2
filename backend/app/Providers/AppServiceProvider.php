<?php

namespace App\Providers;

use App\Services\Ai\AiGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiGateway::class, fn () => new AiGateway);
    }

    public function boot(): void
    {
        RateLimiter::for('ai-actions', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json([
                'message' => 'Too many AI requests. Please wait before trying again.',
            ], 429)));
    }
}
