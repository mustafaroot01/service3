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

    public function __construct(private readonly OtpService $otp) {}

    public function register(array $data): array
    {
        $phone = Phone::international($data['phone']);

        /**
         * A taken number is refused outright. Signing up over an existing row
         * used to be allowed while it was unverified, but that let anyone who
         * knew the number overwrite the name and the password on someone
         * else's account — and since every account is opened verified, the
         * branch only ever served as that handhold.
         */
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر',
            ]);
        }

        $user = DB::transaction(function () use ($data, $phone) {
            $user = new User;

            $user->fill([
                'name' => $data['name'],
                'gender' => $data['gender'],
                'phone' => $phone,
                'password' => $data['password'],
                'governorate_id' => $data['governorate_id'],
                'district_id' => $data['district_id'],
                'terms_accepted_at' => now(),
            ]);

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

        // No token here: signing up and signing in are two deliberate steps,
        // so the app sends the new customer to the login screen.
        return ['phone' => $user->phone];
    }

    /**
     * Only for password recovery. A code is never sent for signup any more, so
     * resending is the same act as asking for one — and both refuse unknown
     * numbers, otherwise anyone could make the server send a WhatsApp message
     * to any Iraqi number at our expense.
     */
    public function resend(string $phone): array
    {
        return $this->forgotPassword($phone);
    }

    private function requireAccountFor(string $phone): User
    {
        return User::where('phone', $phone)->firstOr(fn () => throw ValidationException::withMessages([
            'phone' => 'لا يوجد حساب بهذا الرقم',
        ]));
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

        $this->requireAccountFor($phone);

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
