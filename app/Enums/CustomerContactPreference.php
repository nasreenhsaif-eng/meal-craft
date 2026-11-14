<?php

namespace App\Enums;

enum CustomerContactPreference: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::Phone => __('Phone'),
            self::Whatsapp => __('WhatsApp'),
        };
    }
}
