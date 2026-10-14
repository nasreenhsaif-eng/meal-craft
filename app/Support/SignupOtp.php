<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SignupVerificationCode;
use App\Services\WhatsAppOtpSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Throwable;

final class SignupOtp
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * Issue one code and send it to email and WhatsApp.
     *
     * @return array{whatsapp: string}
     */
    public static function issue(User $user): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::cacheKey($user), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $user->notify(new SignupVerificationCode($code));

        $phone = (string) ($user->customerProfile?->phone ?? '');

        if ($phone === '') {
            $whatsapp = 'failed';
        } else {
            try {
                $whatsapp = app(WhatsAppOtpSender::class)->send($phone, $code);
            } catch (Throwable $exception) {
                report($exception);
                $whatsapp = 'failed';
            }
        }

        if (self::shouldPreviewOnScreen()) {
            session(['signup_otp_preview' => $code]);
        }

        return ['whatsapp' => $whatsapp];
    }

    public static function shouldPreviewOnScreen(): bool
    {
        if (! app()->isLocal()) {
            return false;
        }

        $mailIsLog = config('mail.default') === 'log';
        $whatsappLive = app(WhatsAppOtpSender::class)->deliversOverHttp();

        return $mailIsLog || ! $whatsappLive;
    }

    public static function previewCode(): ?string
    {
        $code = session('signup_otp_preview');

        return is_string($code) && preg_match('/^\d{6}$/', $code) === 1 ? $code : null;
    }

    public static function forgetPreview(): void
    {
        session()->forget('signup_otp_preview');
    }

    /**
     * @return 'ok'|'expired'|'invalid'|'locked'
     */
    public static function check(User $user, string $code): string
    {
        $key = self::cacheKey($user);
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['hash']) || ! is_string($payload['hash'])) {
            return 'expired';
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return 'locked';
        }

        if (! Hash::check($code, $payload['hash'])) {
            $attempts++;

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                return 'locked';
            }

            Cache::put($key, [
                'hash' => $payload['hash'],
                'attempts' => $attempts,
            ], now()->addMinutes(self::TTL_MINUTES));

            return 'invalid';
        }

        Cache::forget($key);

        return 'ok';
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::cacheKey($user));
    }

    private static function cacheKey(User $user): string
    {
        return 'signup-otp:'.$user->getKey();
    }
}
