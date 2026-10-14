<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SignupOtp;
use Illuminate\Auth\Events\Registered;
use Throwable;

class SendSignupVerificationCode
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->needsSignupVerification()) {
            return;
        }

        $user->loadMissing('customerProfile');

        try {
            $result = SignupOtp::issue($user);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('warning', __('We could not send your verification code. Use resend to try again.'));

            return;
        }

        if ($result['whatsapp'] === 'failed' && ! SignupOtp::shouldPreviewOnScreen()) {
            session()->flash('warning', __('We emailed your code, but WhatsApp could not be delivered. You can resend from the next screen.'));
        }
    }
}
