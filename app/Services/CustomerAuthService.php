<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
{
    private const TOKEN_NAME = 'customer';

    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 600;

    private const SIGNUP_TTL = 600;

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Nothing is created here. The signup is held for ten minutes and the code
     * is sent; the account is born only when the code proves the phone, so a
     * half-made row never lingers and there is no "awaiting verification" state
     * to exist at all. The password is hashed before it is cached, so a plain
     * one is never stored anywhere.
     */
    public function register(array $data): array
    {
        $phone = Phone::international($data['phone']);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر',
            ]);
        }

        Cache::put($this->signupKey($phone), [
            'name' => $data['name'],
            'gender' => $data['gender'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'governorate_id' => $data['governorate_id'],
            'district_id' => $data['district_id'],
        ], self::SIGNUP_TTL);

        $this->otp->send($phone, OtpPurpose::REGISTER);

        return [
            'phone' => $phone,
            'resend_in' => $this->otp->secondsUntilResend($phone, OtpPurpose::REGISTER),
        ];
    }

    /**
     * The code proved the phone, so the account is created — active from birth —
     * and the customer is signed straight in.
     */
    public function verifyRegistration(string $phone, string $code): array
    {
        $phone = Phone::international($phone);

        $signup = Cache::get($this->signupKey($phone)) ?? throw ValidationException::withMessages([
            'phone' => 'انتهت جلسة التسجيل، أعد إدخال بياناتك',
        ]);

        $this->otp->verify($phone, OtpPurpose::REGISTER, $code);

        $user = DB::transaction(function () use ($signup) {
            $user = new User;

            // forceFill because password is already hashed and status is set
            // directly; the hashed cast keeps an already-hashed value as-is.
            $user->forceFill([
                'name' => $signup['name'],
                'gender' => $signup['gender'],
                'phone' => $signup['phone'],
                'password' => $signup['password'],
                'governorate_id' => $signup['governorate_id'],
                'district_id' => $signup['district_id'],
                'terms_accepted_at' => now(),
                'status' => UserStatus::ACTIVE,
            ]);

            $user->save();

            return $user;
        });

        Cache::forget($this->signupKey($phone));

        return $this->issueToken($user);
    }

    /**
     * One button for both code screens. A signup still in progress lives in the
     * cache, not as an account; anything else is a password-recovery resend.
     */
    public function resend(string $phone): array
    {
        $phone = Phone::international($phone);

        if (Cache::has($this->signupKey($phone))) {
            $this->otp->send($phone, OtpPurpose::REGISTER);

            return [
                'phone' => $phone,
                'purpose' => 'register',
                'resend_in' => $this->otp->secondsUntilResend($phone, OtpPurpose::REGISTER),
            ];
        }

        return ['purpose' => 'reset', ...$this->forgotPassword($phone)];
    }

    private function requireAccountFor(string $phone): User
    {
        return User::where('phone', $phone)->firstOr(fn () => throw ValidationException::withMessages([
            'phone' => 'لا يوجد حساب بهذا الرقم',
        ]));
    }

    /**
     * Every code costs a WhatsApp message. An account that cannot log in once it
     * holds the code has no use for one, so it is refused before the provider is
     * called and told why instead.
     */
    private function guardCodeIsWorthSending(User $user): void
    {
        $message = match (true) {
            $user->status === UserStatus::SCHEDULED_FOR_DELETION => 'حسابك مجدول للحذف',
            $user->status !== UserStatus::ACTIVE => 'حسابك غير مفعّل، راجع الإدارة',
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['phone' => $message]);
        }
    }

    /**
     * Records the wish and ends the session. Nothing is removed — the account
     * and its orders stay untouched until an admin decides.
     */
    public function requestDeletion(User $user): void
    {
        if ($user->deletion_requested_at !== null) {
            throw ValidationException::withMessages([
                'account' => 'طلب حذف الحساب مُرسل مسبقاً وقيد المراجعة',
            ]);
        }

        $user->forceFill([
            'status' => UserStatus::SCHEDULED_FOR_DELETION,
            'deletion_requested_at' => now(),
        ])->save();

        $user->tokens()->delete();
    }

    public function login(string $phone, string $password): array
    {
        $phone = Phone::international($phone);

        // Keyed on the account being attacked, never the caller: an Iraqi mobile
        // is trivially enumerable and the password only has to be eight
        // characters, so an unthrottled login is a dictionary attack waiting to
        // run. Only wrong passwords count, and a correct one clears the tally —
        // an honest customer is never locked out by his own successful logins.
        $throttleKey = 'customer-login:'.$phone;

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => 'محاولات دخول كثيرة، حاول بعد '.RateLimiter::availableIn($throttleKey).' ثانية',
            ]);
        }

        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف أو كلمة السر غير صحيحة',
            ]);
        }

        // The password was right, so this is the account's owner, not an
        // attacker — reset the counter before the status gates below, which turn
        // a valid password away for reasons that are not brute force.
        RateLimiter::clear($throttleKey);

        if ($user->status === UserStatus::SCHEDULED_FOR_DELETION) {
            throw ValidationException::withMessages([
                'phone' => 'حسابك مجدول للحذف',
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'phone' => 'حسابك غير مفعّل، راجع الإدارة',
            ]);
        }

        return $this->issueToken($user);
    }

    public function forgotPassword(string $phone): array
    {
        $phone = Phone::international($phone);

        $user = $this->requireAccountFor($phone);

        $this->guardCodeIsWorthSending($user);

        $this->otp->send($phone, OtpPurpose::RESET);

        return [
            'phone' => $phone,
            'resend_in' => $this->otp->secondsUntilResend($phone, OtpPurpose::RESET),
        ];
    }

    /**
     * Every existing session dies with the old password, and no new one is
     * handed out: whoever reset it proves the new password on the login screen.
     */
    public function resetPassword(string $phone, string $code, string $password): void
    {
        $phone = Phone::international($phone);
        $user = $this->requireAccountFor($phone);

        $this->otp->verify($phone, OtpPurpose::RESET, $code);

        DB::transaction(function () use ($user, $password) {
            $user->password = $password;
            $user->save();
            $user->tokens()->delete();
        });
    }

    private function signupKey(string $phone): string
    {
        return 'signup:'.$phone;
    }

    private function issueToken(User $user): array
    {
        $user->load(['governorate', 'district']);

        return [
            'user' => $user,
            'token' => $user->createToken(self::TOKEN_NAME)->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
