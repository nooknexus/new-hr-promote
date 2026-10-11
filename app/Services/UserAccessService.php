<?php

namespace App\Services;

use App\Support\AdminAccess;
use App\Support\OfficeScope;
use App\Support\PersonnelTypeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Role, office, status and personnel-type changes made on /admin/users.
 * SUPERADMIN and ADMIN may change users, but only a SUPERADMIN may grant the
 * SUPERADMIN role or change a SUPERADMIN account, and the last active
 * SUPERADMIN can never be demoted or disabled.
 */
class UserAccessService
{
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

    /**
     * Replace the personnel types a user is responsible for on salary promotions.
     *
     * @param  list<int|string>  $typeIds
     */
    public function updatePersonnelTypes(int $actorId, int $targetUserId, array $typeIds): void
    {
        $types = array_values(array_unique(array_map('intval', $typeIds)));
        sort($types);
        if (array_diff($types, array_keys(PersonnelTypeScope::TYPES)) !== [] || array_filter($typeIds, fn ($id) => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['personnelTypes' => 'ประเภทการจ้างไม่ถูกต้อง']);
        }

        DB::transaction(function () use ($actorId, $targetUserId, $types): void {
            $actor = $this->lockAdministrators()->firstWhere('id', $actorId);
            abort_unless($actor && $actor->is_active, 403);
            $target = DB::table('users')->where('id', $targetUserId)->lockForUpdate()->first(['id', 'role', 'office', 'is_active']);
            abort_unless($target, 404);
            abort_if($target->role === AdminAccess::SUPERADMIN && $actor->role !== AdminAccess::SUPERADMIN, 403);

            $before = PersonnelTypeScope::assignedTo($targetUserId);
            if ($before === $types) {
                return;
            }

            DB::table('user_personnel_types')->where('user_id', $targetUserId)->delete();
            DB::table('user_personnel_types')->insert(array_map(fn (int $type) => ['user_id' => $targetUserId, 'type_id' => $type], $types));
            DB::table('user_access_audits')->insert([
                'actor_id' => $actorId, 'target_user_id' => $targetUserId,
                'before_role' => $target->role, 'after_role' => $target->role,
                'before_office' => $target->office, 'after_office' => $target->office,
                'before_active' => (bool) $target->is_active, 'after_active' => (bool) $target->is_active,
                'before_personnel_types' => implode(',', $before), 'after_personnel_types' => implode(',', $types),
                'created_at' => now(),
            ]);
        });
    }

    private function change(int $actorId, int $targetUserId, array $changes): void
    {
        DB::transaction(function () use ($actorId, $targetUserId, $changes): void {
            $administrators = $this->lockAdministrators();
            $actor = $administrators->firstWhere('id', $actorId);
            abort_unless($actor && $actor->is_active, 403);
            $activeSuperadmins = $administrators->filter(fn ($user): bool => $user->role === AdminAccess::SUPERADMIN && (bool) $user->is_active);

            if (array_key_exists('role', $changes) && $changes['role'] !== null && ! in_array($changes['role'], AdminAccess::ROLES, true)) {
                throw ValidationException::withMessages(['role' => 'บทบาทไม่ถูกต้อง']);
            }
            $target = DB::table('users')->where('id', $targetUserId)->lockForUpdate()->first(['id', 'role', 'office', 'is_active']);
            abort_unless($target, 404);

            // Only a SUPERADMIN grants SUPERADMIN or touches a SUPERADMIN account.
            if ($actor->role !== AdminAccess::SUPERADMIN
                && ($target->role === AdminAccess::SUPERADMIN || ($changes['role'] ?? null) === AdminAccess::SUPERADMIN)) {
                abort(403);
            }

            // A new office must be active in the registry; keeping a user's
            // current (possibly deactivated) office is always allowed.
            if (isset($changes['office']) && $changes['office'] !== $target->office
                && (strlen($changes['office']) > 2 || ! OfficeScope::exists($changes['office']))) {
                throw ValidationException::withMessages(['office' => 'ไม่พบต้นสังกัดที่เลือก หรือต้นสังกัดถูกปิดใช้งาน']);
            }
            // Null clears a field; omitted fields retain the locked current value.
            $role = array_key_exists('role', $changes) ? $changes['role'] : $target->role;
            $office = array_key_exists('office', $changes) ? $changes['office'] : $target->office;
            $active = $changes['is_active'] ?? (bool) $target->is_active;

            if (! $active && $actorId === $targetUserId) {
                throw ValidationException::withMessages(['status' => 'ไม่สามารถปิดการใช้งานบัญชีของตนเองได้']);
            }
            if ($target->role === AdminAccess::SUPERADMIN && (bool) $target->is_active
                && ($role !== AdminAccess::SUPERADMIN || ! $active) && $activeSuperadmins->count() === 1) {
                throw ValidationException::withMessages(['role' => 'ไม่สามารถเปลี่ยนสิทธิ์หรือปิด SUPERADMIN ที่ใช้งานได้คนสุดท้าย']);
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

    /** Lock every SUPERADMIN and ADMIN row in a stable order shared by all mutations. */
    private function lockAdministrators(): \Illuminate\Support\Collection
    {
        return DB::table('users')->whereIn('role', [AdminAccess::SUPERADMIN, AdminAccess::ADMIN])
            ->orderBy('id')->lockForUpdate()->get(['id', 'role', 'is_active']);
    }
}
