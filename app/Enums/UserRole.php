<?php

namespace App\Enums;

enum UserRole: string
{
    case CraftMasterGeneral = 'craft_master_general';
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::CraftMasterGeneral => __('Craft Master General'),
            self::Admin => __('Admin'),
            self::Staff => __('Staff'),
            self::Customer => __('Customer'),
        };
    }

    public function isStaff(): bool
    {
        return $this === self::CraftMasterGeneral
            || $this === self::Admin
            || $this === self::Staff;
    }

    public function canAccessAdminPortal(): bool
    {
        return $this === self::CraftMasterGeneral || $this === self::Admin;
    }

    public function isCraftMasterGeneral(): bool
    {
        return $this === self::CraftMasterGeneral;
    }

    public function isCustomer(): bool
    {
        return $this === self::Customer;
    }
}
