<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    /** Full access to every admin screen and every office. */
    public const SUPERADMIN = 'SUPERADMIN';

    /** System administration only: offices, users and signatories, for every office. */
    public const ADMIN = 'ADMIN';

    public const ROLES = [self::SUPERADMIN, self::ADMIN, 'LEAVE', 'PROMOTE', 'FINANCE'];

    private const ROLE_ABILITIES = [
        self::ADMIN => ['offices', 'users', 'signatories'],
        // Staff access is limited to the user's office (see OfficeScope).
        'LEAVE' => ['leave', 'leave-sync', 'personnel'],
        'PROMOTE' => ['promotions', 'personnel'],
        'FINANCE' => ['signatories', 'tax-certificates', 'payslip', 'personnel'],
    ];

    private const ABILITIES = ['users', 'personnel', 'leave', 'leave-sync', 'promotions', 'signatories', 'tax-certificates', 'payslip', 'offices'];

    private const ROLE_LABELS = [
        self::SUPERADMIN => 'ผู้ดูแลระบบสูงสุด',
        self::ADMIN => 'ผู้ดูแลระบบ',
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

        if ($user->role === self::SUPERADMIN) {
            return true;
        }
        if ($user->role === self::ADMIN) {
            return in_array($ability, self::ROLE_ABILITIES[self::ADMIN], true);
        }

        // Signatories are shared system-wide; staff without an office have no access.
        if ($ability === 'signatories' && OfficeScope::officeFor($user) === '') {
            return false;
        }

        // A Google Sheet sync writes leave for every office, so only unrestricted
        // users (SUPERADMIN or office 00) may run it.
        if ($ability === 'leave-sync' && OfficeScope::officeFor($user) !== null) {
            return false;
        }

        // The office registry affects every office, so any staff role in office 00 may manage it.
        if ($ability === 'offices') {
            return isset(self::ROLE_ABILITIES[$user->role ?? '']) && OfficeScope::officeFor($user) === null;
        }

        return in_array($ability, self::ROLE_ABILITIES[$user->role ?? ''] ?? [], true);
    }

    /** SUPERADMIN and ADMIN act on every office in the screens they can open. */
    public static function isAdministrator(?User $user): bool
    {
        return in_array($user?->role, [self::SUPERADMIN, self::ADMIN], true);
    }
}
