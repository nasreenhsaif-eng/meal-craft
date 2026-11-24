<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a signup OTP through the WhatsApp Cloud API.
 *
 * Plaintext codes are logged only in local and testing when HTTP is not used.
 */
final class WhatsAppOtpSender
{
    public function deliversOverHttp(): bool
    {
        return $this->shouldSendOverHttp();
    }

    public function send(string $phone, string $code): string
    {
        if ($this->shouldSendOverHttp()) {
            return $this->post($phone, $code);
        }

        if (app()->environment('local', 'testing')) {
            Log::info('signup otp whatsapp', [
                'phone' => $phone,
                'code' => $code,
            ]);

            return 'logged';
        }

        return 'failed';
    }

    private function shouldSendOverHttp(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return (bool) config('services.whatsapp.send_http', false);
        }

        return true;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.otp_template'));
    }

    private function post(string $phone, string $code): string
    {
        $version = (string) config('services.whatsapp.graph_version', 'v21.0');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => (string) config('services.whatsapp.otp_template'),
                        'language' => [
                            'code' => (string) config('services.whatsapp.otp_template_language', 'en'),
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException|Throwable $exception) {
            report($exception);

            return 'failed';
        }

        if (! $response->successful()) {
            Log::warning('WhatsApp signup OTP was not accepted.', [
                'status' => $response->status(),
            ]);

            return 'failed';
        }

        return 'sent';
    }
}
