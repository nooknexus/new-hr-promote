<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Limits /admin/salary-promotions to the personnel types (person.type_id) a
 * user is responsible for, on top of OfficeScope. ADMIN is unrestricted;
 * anyone else sees only the types assigned in user_personnel_types.
 */
final class PersonnelTypeScope
{
    public const TYPES = [
        1 => 'ข้าราชการ',
        2 => 'ลูกจ้างประจำ',
        3 => 'พนักงานราชการ',
        4 => 'พนักงานกระทรวง',
    ];

    /** @return list<int>|null null for every type, [] for none. */
    public static function typesFor(?User $user): ?array
    {
        if ($user?->role === 'ADMIN') {
            return null;
        }

        return $user === null ? [] : self::assignedTo((int) $user->id);
    }

    /** @return list<int> */
    public static function assignedTo(int $userId): array
    {
        return DB::table('user_personnel_types')->where('user_id', $userId)
            ->orderBy('type_id')->pluck('type_id')->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => isset(self::TYPES[$id]))->values()->all();
    }

    /** Constrain a query on a person type column to the user's types. */
    public static function apply(Builder $query, ?User $user, string $typeColumn = 'type_id'): Builder
    {
        $types = self::typesFor($user);

        return $query->when($types !== null, fn (Builder $query) => $types === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($typeColumn, $types));
    }

    /** @return array<int, string> Types the user may filter or work with. */
    public static function options(?User $user): array
    {
        $types = self::typesFor($user);

        return $types === null ? self::TYPES : array_intersect_key(self::TYPES, array_flip($types));
    }

    /** @param  list<int>  $types */
    public static function labels(array $types): string
    {
        return implode(', ', array_map(fn (int $id) => self::TYPES[$id] ?? (string) $id, $types));
    }
}
