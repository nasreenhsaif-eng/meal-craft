<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Staff => __('Staff'),
            self::Customer => __('Customer'),
        };
    }

    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Staff;
    }

    public function isCustomer(): bool
    {
        return $this === self::Customer;
    }
}
