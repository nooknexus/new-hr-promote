<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Offices from the offices registry that the user may see, as objects with
     * office_id, office_name and is_active. Filters pass $includeInactive so old
     * records stay reachable; forms list only active offices.
     *
     * @return Collection<int, object{office_id: string, office_name: string, is_active: bool}>
     */
    public static function options(?User $user, bool $includeInactive = false): Collection
    {
        return self::apply(DB::table('offices'), $user, 'code')
            ->when(! $includeInactive, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'name', 'is_active'])
            ->map(fn (object $office): object => (object) [
                'office_id' => (string) $office->code,
                'office_name' => (string) $office->name,
                'is_active' => (bool) $office->is_active,
            ]);
    }

    public static function exists(?string $code, bool $activeOnly = true): bool
    {
        return $code !== null && $code !== '' && DB::table('offices')
            ->where('code', $code)
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->exists();
    }

    public static function nameFor(?string $code): ?string
    {
        return $code === null || $code === '' ? null : DB::table('offices')->where('code', $code)->value('name');
    }
}
