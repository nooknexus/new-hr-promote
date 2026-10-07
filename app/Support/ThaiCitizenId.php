<?php

namespace App\Support;

final class ThaiCitizenId
{
    public static function display(string $id): string
    {
        if (! preg_match('/\A[0-9]{13}\z/', $id)) {
            return $id;
        }

        return substr($id, 0, 1) . '-'
            . substr($id, 1, 4) . '-'
            . substr($id, 5, 5) . '-'
            . substr($id, 10, 2) . '-'
            . substr($id, 12, 1);
    }
}
