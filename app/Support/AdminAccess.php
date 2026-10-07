<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    private const ROLE_ABILITIES = [
        // Non-ADMIN personnel access is limited to the user's office (see PersonnelManagement).
        'LEAVE' => ['leave', 'leave-sync', 'personnel'],
        'PROMOTE' => ['promotions', 'personnel'],
        'FINANCE' => ['signatories', 'tax-certificates', 'payslip', 'personnel'],
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

        // Signatories are shared system-wide; a non-ADMIN without an office has no access.
        if ($ability === 'signatories' && OfficeScope::officeFor($user) === '') {
            return false;
        }

        // A Google Sheet sync writes leave for every office, so only unrestricted
        // users (ADMIN or office 00) may run it.
        if ($ability === 'leave-sync' && OfficeScope::officeFor($user) !== null) {
            return false;
        }

        return in_array($ability, self::ROLE_ABILITIES[$user->role ?? ''] ?? [], true);
    }
}
