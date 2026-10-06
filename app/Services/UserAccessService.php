<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    private const ROLES = ['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'];

    public function update(int $actorId, int $targetUserId, ?string $role, ?string $office): void
    {
        $this->change($actorId, $targetUserId, ['role' => $role, 'office' => $office]);
    }

    public function updateRole(int $actorId, int $targetUserId, ?string $role): void
    {
        $this->change($actorId, $targetUserId, ['role' => $role]);
    }

    public function updateOffice(int $actorId, int $targetUserId, ?string $office): void
    {
        $this->change($actorId, $targetUserId, ['office' => $office]);
    }

    public function setActive(int $actorId, int $targetUserId, bool $active): void
    {
        $this->change($actorId, $targetUserId, ['is_active' => $active]);
    }

    private function change(int $actorId, int $targetUserId, array $changes): void
    {
        DB::transaction(function () use ($actorId, $targetUserId, $changes): void {
            // All administrator mutations share this stable lock order.
            $admins = DB::table('users')->where('role', 'ADMIN')->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
            $activeAdmins = $admins->filter(fn ($user): bool => (bool) $user->is_active);
            abort_unless($activeAdmins->contains('id', $actorId), 403);

            if (array_key_exists('role', $changes) && $changes['role'] !== null && ! in_array($changes['role'], self::ROLES, true)) {
                throw ValidationException::withMessages(['role' => 'บทบาทไม่ถูกต้อง']);
            }
            if (isset($changes['office']) && (strlen($changes['office']) > 2 || ! DB::table('person')->where('office_id', $changes['office'])->exists())) {
                throw ValidationException::withMessages(['office' => 'ไม่พบหน่วยงานที่เลือก']);
            }

            $target = DB::table('users')->where('id', $targetUserId)->lockForUpdate()->first(['id', 'role', 'office', 'is_active']);
            abort_unless($target, 404);
            // Null clears a field; omitted fields retain the locked current value.
            $role = array_key_exists('role', $changes) ? $changes['role'] : $target->role;
            $office = array_key_exists('office', $changes) ? $changes['office'] : $target->office;
            $active = $changes['is_active'] ?? (bool) $target->is_active;

            if (! $active && $actorId === $targetUserId) {
                throw ValidationException::withMessages(['status' => 'ไม่สามารถปิดการใช้งานบัญชีของตนเองได้']);
            }
            if ($target->role === 'ADMIN' && (bool) $target->is_active && ($role !== 'ADMIN' || ! $active) && $activeAdmins->count() === 1) {
                throw ValidationException::withMessages(['role' => 'ไม่สามารถเปลี่ยนสิทธิ์หรือปิด ADMIN ที่ใช้งานได้คนสุดท้าย']);
            }
            if ($target->role === $role && $target->office === $office && (bool) $target->is_active === $active) {
                return;
            }

            $data = ['role' => $role, 'office' => $office, 'is_active' => $active, 'updated_at' => now()];
            if (! $active) {
                $data['remember_token'] = null;
            }
            DB::table('users')->where('id', $targetUserId)->update($data);
            if (! $active && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $targetUserId)->delete();
            }
            DB::table('user_access_audits')->insert([
                'actor_id' => $actorId, 'target_user_id' => $targetUserId,
                'before_role' => $target->role, 'after_role' => $role,
                'before_office' => $target->office, 'after_office' => $office,
                'before_active' => (bool) $target->is_active, 'after_active' => $active,
                'created_at' => now(),
            ]);
        });
    }
}
