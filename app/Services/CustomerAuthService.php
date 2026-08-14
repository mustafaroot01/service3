<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
{
    private const TOKEN_NAME = 'customer';

    public function __construct(private readonly OtpService $otp)
    {
    }

    public function register(array $data): array
    {
        $phone = Phone::international($data['phone']);
        $existing = User::where('phone', $phone)->first();

        if ($existing && $existing->phone_verified_at !== null) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر',
            ]);
        }

        $user = DB::transaction(function () use ($data, $phone, $existing) {
            $attributes = [
                'name' => $data['name'],
                'gender' => $data['gender'],
                'phone' => $phone,
                'password' => $data['password'],
                'governorate_id' => $data['governorate_id'],
                'district_id' => $data['district_id'],
                'terms_accepted_at' => now(),
            ];

            $user = $existing ?: new User;
            $user->fill($attributes);

            // The number is taken as given at signup: no code is sent and the
            // account opens straight away, so registration never waits on the
            // messaging provider.
            $user->forceFill([
                'status' => UserStatus::ACTIVE,
                'phone_verified_at' => now(),
            ]);

            $user->save();

            return $user;
        });

        return $this->issueToken($user);
    }

    public function resend(string $phone, OtpPurpose $purpose): array
    {
        $phone = Phone::international($phone);

        $this->otp->send($phone, $purpose);

        return [
            'phone' => $phone,
            'resend_in' => $this->otp->secondsUntilResend($phone, $purpose),
        ];
    }

    public function verifyRegistration(string $phone, string $code): array
    {
        $phone = Phone::international($phone);
        $user = User::where('phone', $phone)->firstOr(fn () => throw ValidationException::withMessages([
            'phone' => 'لا يوجد حساب بهذا الرقم',
        ]));

        $this->otp->verify($phone, OtpPurpose::REGISTER, $code);

        $user->forceFill([
            'phone_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ])->save();

        return $this->issueToken($user);
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
        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف أو كلمة السر غير صحيحة',
            ]);
        }

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

        if (! User::where('phone', $phone)->whereNotNull('phone_verified_at')->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'لا يوجد حساب موثّق بهذا الرقم',
            ]);
        }

        $this->otp->send($phone, OtpPurpose::RESET);

        return [
            'phone' => $phone,
            'resend_in' => $this->otp->secondsUntilResend($phone, OtpPurpose::RESET),
        ];
    }

    public function resetPassword(string $phone, string $code, string $password): array
    {
        $phone = Phone::international($phone);
        $user = User::where('phone', $phone)->firstOr(fn () => throw ValidationException::withMessages([
            'phone' => 'لا يوجد حساب بهذا الرقم',
        ]));

        $this->otp->verify($phone, OtpPurpose::RESET, $code);

        DB::transaction(function () use ($user, $password) {
            $user->password = $password;
            $user->save();
            $user->tokens()->delete();
        });

        return $this->issueToken($user);
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
