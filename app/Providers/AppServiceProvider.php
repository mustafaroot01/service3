<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Technician;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'admin' => Admin::class,
            'user' => User::class,
            'technician' => Technician::class,
        ]);

        $this->registerRateLimiters();
    }

    /**
     * Laravel's inline throttle:x,y keys every unauthenticated route by IP alone,
     * so all auth routes would share one bucket and carrier-grade NAT would lock
     * out whole networks. These limiters key on the phone number instead.
     */
    private function registerRateLimiters(): void
    {
        $phone = fn (Request $request) => Phone::international($request->input('phone')) ?? $request->ip();

        // Laravel's own answer is the English "Too Many Attempts.", and this one
        // is read by a customer waiting on a code, not by a developer.
        $tooMany = fn (string $message) => fn () => ApiResponse::error($message, ['phone' => [$message]], 429);

        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinutes(10, 4)
            ->by('otp-send:'.$phone($request))
            ->response($tooMany('طلبت رموزاً كثيرة، حاول بعد عشر دقائق')));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 8)
            ->by('otp-verify:'.$phone($request))
            ->response($tooMany('حاولت مرات كثيرة، انتظر عشر دقائق ثم أعد المحاولة')));

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(20)
            ->by('admin-login:'.strtolower((string) $request->input('email'))));
    }
}
