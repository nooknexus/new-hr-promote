<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    private const ROLE_ABILITIES = [
        'LEAVE' => ['leave', 'leave-sync'],
        'PROMOTE' => ['promotions'],
        'FINANCE' => ['signatories', 'tax-certificates', 'payslip'],
    ];

    private const ABILITIES = ['users', 'personnel', 'leave', 'leave-sync', 'promotions', 'signatories', 'tax-certificates', 'payslip'];

    public static function allows(?User $user, string $ability): bool
    {
        if ($user === null || $user->is_active === false || ! in_array($ability, self::ABILITIES, true)) {
            return false;
        }

        if ($user->role === 'ADMIN') {
            return true;
        }

        return in_array($ability, self::ROLE_ABILITIES[$user->role ?? ''] ?? [], true);
    }
}
