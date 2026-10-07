<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LeaveTypeCatalog
{
    public const ADDITIONAL_TYPES = [
        'ลาคลอดบุตร',
        'ลาไปช่วยเหลือภริยาที่คลอดบุตร',
        'ลาอุปสมบท',
        'ลาศึกษา',
    ];

    public static function ensureAdditionalTypes(): void
    {
        $existingNames = DB::table('leave_types')
            ->whereIn('name', self::ADDITIONAL_TYPES)
            ->pluck('name')
            ->all();

        foreach (array_diff(self::ADDITIONAL_TYPES, $existingNames) as $index => $name) {
            // Match the sync code so either entry path can register the type first.
            DB::table('leave_types')->insertOrIgnore([
                'name' => $name,
                'code' => 'SYNC_' . substr(hash('sha256', mb_strtolower($name)), 0, 15),
                'is_active' => true,
                'sort_order' => 10 + $index,
            ]);
        }
    }
}
