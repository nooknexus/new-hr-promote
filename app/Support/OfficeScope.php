<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Limits admin screens to the personnel of the user's office.
 * ADMIN and users assigned to office 00 are unrestricted; any other user
 * without an assigned office matches nothing.
 */
final class OfficeScope
{
    public const ALL_OFFICES = '00';

    /** The office the user may manage, null for every office, or '' for none. */
    public static function officeFor(?User $user): ?string
    {
        $office = trim((string) $user?->office);

        return $user?->role === 'ADMIN' || $office === self::ALL_OFFICES ? null : $office;
    }

    /** Constrain a query on a person office column to the user's office. */
    public static function apply(Builder $query, ?User $user, string $officeColumn = 'office_id'): Builder
    {
        $office = self::officeFor($user);

        return $query->when($office !== null, fn (Builder $query) => $office === ''
            ? $query->whereRaw('1 = 0')
            : $query->where($officeColumn, $office));
    }
}
