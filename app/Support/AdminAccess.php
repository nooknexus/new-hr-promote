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

    private const ABILITIES = ['users', 'personnel', 'leave', 'leave-sync', 'promotions', 'signatories', 'tax-certificates', 'payslip', 'offices'];

    private const ROLE_LABELS = [
        'ADMIN' => 'ผู้ดูแลระบบ',
        'LEAVE' => 'เจ้าหน้าที่งานลา',
        'PROMOTE' => 'เจ้าหน้าที่งานเลื่อนขั้นเงินเดือน',
        'FINANCE' => 'เจ้าหน้าที่การเงิน',
    ];

    /** Thai label for the user's local role; users without a role are regular users. */
    public static function roleLabel(?User $user): string
    {
        return self::ROLE_LABELS[$user?->role ?? ''] ?? 'ผู้ใช้งานทั่วไป';
    }

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

        // The office registry affects every office, so any staff role in office 00 may manage it.
        if ($ability === 'offices') {
            return isset(self::ROLE_ABILITIES[$user->role ?? '']) && OfficeScope::officeFor($user) === null;
        }

        return in_array($ability, self::ROLE_ABILITIES[$user->role ?? ''] ?? [], true);
    }
}
