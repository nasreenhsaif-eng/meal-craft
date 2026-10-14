<?php

namespace App\Notifications;

use App\Support\SignupOtp;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignupVerificationCode extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Meal Craft verification code'))
            ->line(__('Your verification code is :code.', ['code' => $this->code]))
            ->line(__('This code expires in :minutes minutes. The same code was sent to your WhatsApp number.', [
                'minutes' => SignupOtp::TTL_MINUTES,
            ]));
    }
}
