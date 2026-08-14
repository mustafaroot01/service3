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

        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinutes(10, 4)
            ->by('otp-send:'.$phone($request)));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 8)
            ->by('otp-verify:'.$phone($request)));

        /**
         * Counted per phone, never per address. Iraqi carriers put thousands of
         * customers behind one address, so an address cap would lock a whole
         * network out after three applications — and it fired before validation
         * could tell an applicant his number was already on file.
         */
        RateLimiter::for('technician-application', fn (Request $request) => Limit::perHour(3)
            ->by('tech-app:'.$phone($request))
            ->response(fn () => ApiResponse::error(
                'حاول بعد قليل، أرسلت طلبات كثيرة بهذا الرقم',
                ['phone' => ['حاول بعد قليل، أرسلت طلبات كثيرة بهذا الرقم']],
                429
            )));

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(20)
            ->by('admin-login:'.strtolower((string) $request->input('email'))));
    }
}
