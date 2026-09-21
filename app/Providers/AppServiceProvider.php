<?php

namespace App\Providers;

use App\Models\Staff;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination-simple');

        // NFR01, T218: https for every generated URL, including Vite assets and
        // the Reverb endpoint, once the app is served over TLS (10.2 step 7).
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(function ($user, string $ability) {
            return $user instanceof Staff && $user->role->role_name === 'admin' ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        Blade::directive('money', function ($expression) {
            return "<?php echo \App\Support\Money::format({$expression}); ?>";
        });

        Blade::directive('auDate', function ($expression) {
            return "<?php echo \App\Support\AustralianDate::date({$expression}); ?>";
        });

        Blade::directive('auDateTime', function ($expression) {
            return "<?php echo \App\Support\AustralianDate::dateTime({$expression}); ?>";
        });
    }
}
