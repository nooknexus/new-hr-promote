<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PersonnelMatcher
{
    public function findDisplayNameByCid(string $cid): ?string
    {
        $person = DB::table('person')
            ->where('cid', $cid)
            ->first(['pname', 'fname', 'lname']);

        if ($person === null) {
            return null;
        }

        $name = implode(' ', array_filter(array_map(
            static fn ($part) => trim((string) $part),
            [$person->pname, $person->fname, $person->lname],
        )));

        return $name !== '' ? $name : null;
    }
}
