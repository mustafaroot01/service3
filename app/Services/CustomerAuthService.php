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
        $existing = User::where('phone', $phone)->first();

        /**
         * A verified number belongs to someone. An unverified row does not yet:
         * it is an abandoned signup, and letting the next applicant write over
         * it is safe only because the code below still has to reach the phone
         * before the account opens.
         */
        if ($existing && $existing->phone_verified_at !== null) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر',
            ]);
        }

        $user = DB::transaction(function () use ($data, $phone, $existing) {
            $user = $existing ?: new User;

            $user->fill([
                'name' => $data['name'],
                'gender' => $data['gender'],
                'phone' => $phone,
                'password' => $data['password'],
                'governorate_id' => $data['governorate_id'],
                'district_id' => $data['district_id'],
                'terms_accepted_at' => now(),
            ]);

            $user->forceFill(['status' => UserStatus::PENDING]);
            $user->save();

            return $user;
        });

        // Sent after the row is committed: a failing provider must not take the
        // half-made account down with it — the customer can ask for the code again.
        $this->otp->send($phone, OtpPurpose::REGISTER);

        return [
            'phone' => $user->phone,
            'resend_in' => $this->otp->secondsUntilResend($phone, OtpPurpose::REGISTER),
        ];
    }

    /**
     * Opens the account, and hands back nothing else: signing up and signing in
     * stay two deliberate steps, so the app moves on to the login screen.
     */
    public function verifyRegistration(string $phone, string $code): array
    {
        $phone = Phone::international($phone);
        $user = $this->requireAccountFor($phone);

        if ($user->phone_verified_at !== null) {
            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم موثّق بالفعل، سجّل الدخول',
            ]);
        }

        $this->otp->verify($phone, OtpPurpose::REGISTER, $code);

        $user->forceFill([
            'status' => UserStatus::ACTIVE,
            'phone_verified_at' => now(),
        ])->save();

        return ['phone' => $user->phone];
    }

    /**
     * One button for both code screens. The server picks the purpose from the
     * account itself — an unverified one is still finishing signup — and an
     * unknown number is refused, otherwise anyone could make us send a WhatsApp
     * message to any Iraqi number at our expense.
     */
    public function resend(string $phone): array
    {
        $phone = Phone::international($phone);
        $user = $this->requireAccountFor($phone);

        $purpose = $user->phone_verified_at === null
            ? OtpPurpose::REGISTER
            : OtpPurpose::RESET;

        $this->otp->send($phone, $purpose);

        return [
            'phone' => $phone,
            'purpose' => $purpose->value,
            'resend_in' => $this->otp->secondsUntilResend($phone, $purpose),
        ];
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

        if ($user->phone_verified_at === null) {
            throw ValidationException::withMessages([
                'phone' => 'لم يتم توثيق رقمك بعد، اطلب رمز التحقق',
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

        // Recovering a password one never got to use makes no sense, and the
        // signup code is the right one to finish that account.
        if ($this->requireAccountFor($phone)->phone_verified_at === null) {
            throw ValidationException::withMessages([
                'phone' => 'لم يتم توثيق رقمك بعد، اطلب رمز التحقق',
            ]);
        }

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
