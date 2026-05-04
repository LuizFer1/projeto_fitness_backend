<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\XpTransaction;
use App\Observers\XpTransactionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        XpTransaction::observe(XpTransactionObserver::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('leaderboard', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // SRS RNF-05: 5 login attempts per IP per minute
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json(['message' => 'Muitas tentativas de login. Tente novamente em breve.'], 429);
            });
        });
    }
}
