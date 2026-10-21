<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Payslips read the position number from users.no_position, so a change to
 * person.no_position is copied to the linked account, as the salary promotion
 * import does. An unchanged number never touches the account, and an
 * ambiguous link (several accounts for one CID) is left alone.
 */
final class UserPositionNumber
{
    public static function syncFromPerson(string $cid, ?string $previous, ?string $current): void
    {
        if ($previous === $current) {
            return;
        }

        $users = DB::table('users')->where('pid', $cid)->orWhere('thaid_pid', $cid)->limit(2)->pluck('id');
        if ($users->count() === 1) {
            DB::table('users')->where('id', $users->first())->update(['no_position' => $current]);
        }
    }
}
