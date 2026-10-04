<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly in non-production on lazy loads / unfillable attributes.
        EloquentModel::shouldBeStrict(! $this->app->isProduction());

        // API rate limit (used by throttleApi in bootstrap/app.php): per user, else per IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(90)->by($request->user()?->id ?: $request->ip());
        });
    }
}
