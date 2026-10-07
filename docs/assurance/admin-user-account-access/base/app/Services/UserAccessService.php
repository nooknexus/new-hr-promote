<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    private const ROLES = ['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'];

    public function update(int $actorId, int $targetUserId, ?string $role, ?string $office): void
    {
        if ($role !== null && ! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'บทบาทไม่ถูกต้อง']);
        }

        DB::transaction(function () use ($actorId, $targetUserId, $role, $office): void {
            $adminIds = DB::table('users')->where('role', 'ADMIN')
                ->orderBy('id')->lockForUpdate()->pluck('id');
            abort_unless($adminIds->contains($actorId), 403);

            if ($office !== null && ! DB::table('person')->where('office_id', $office)->exists()) {
                throw ValidationException::withMessages(['office' => 'ไม่พบหน่วยงานที่เลือก']);
            }

            $target = DB::table('users')->where('id', $targetUserId)->lockForUpdate()
                ->first(['id', 'role', 'office']);
            abort_unless($target, 404);

            if ($target->role === 'ADMIN' && $role !== 'ADMIN' && $adminIds->count() === 1) {
                throw ValidationException::withMessages(['role' => 'ไม่สามารถเปลี่ยนบทบาท ADMIN คนสุดท้ายได้']);
            }

            if ($target->role === $role && $target->office === $office) {
                return;
            }

            DB::table('users')->where('id', $targetUserId)->update([
                'role' => $role,
                'office' => $office,
                'updated_at' => now(),
            ]);
            DB::table('user_access_audits')->insert([
                'actor_id' => $actorId,
                'target_user_id' => $targetUserId,
                'before_role' => $target->role,
                'after_role' => $role,
                'before_office' => $target->office,
                'after_office' => $office,
                'created_at' => now(),
            ]);
        });
    }
}
