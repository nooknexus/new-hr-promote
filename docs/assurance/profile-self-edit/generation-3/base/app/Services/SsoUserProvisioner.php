<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class SsoUserProvisioner
{
    /**
     * Creates or refreshes a user only when the SSO CID is an existing personnel CID.
     * Returns null for an untrusted, unregistered, or ambiguously linked identity.
     *
     * @param  array{name?: string, name_en?: string, email?: string, birthdate?: string, address?: string, thaid_pid?: string, provider_id?: string, account_id?: string, hcode?: string, hname?: string, hospcode?: string}  $attributes
     */
    public function provision(string $cid, array $attributes): ?User
    {
        $cid = trim($cid);
        if (! preg_match('/^\d{13}$/', $cid)) {
            return null;
        }

        return DB::transaction(function () use ($cid, $attributes): ?User {
            $person = DB::table('person')->where('cid', $cid)->lockForUpdate()->first();
            if ($person === null) {
                return null;
            }

            $hash = hash('sha256', $cid);
            $matches = User::query()
                ->where(function ($query) use ($cid, $hash, $attributes): void {
                    $query->where('pid', $cid)
                        ->orWhere('thaid_pid', $cid)
                        ->orWhere('cid_hash', $hash);

                    foreach (['provider_id', 'account_id'] as $field) {
                        if (filled($attributes[$field] ?? null)) {
                            $query->orWhere($field, $attributes[$field]);
                        }
                    }
                })
                ->lockForUpdate()
                ->get()
                ->unique('id')
                ->values();

            if ($matches->count() > 1) {
                return null;
            }

            /** @var User|null $user */
            $user = $matches->first();
            if ($user !== null && ! $this->belongsToCid($user, $cid, $hash)) {
                return null;
            }

            $email = $this->availableEmail(
                (string) ($attributes['email'] ?? ''),
                $user?->id,
                $user?->email,
            );
            $name = trim((string) ($attributes['name'] ?? '')) ?: $this->personName($person) ?: 'ผู้ใช้งานระบบ';
            $data = array_filter([
                'name' => $name,
                'name_en' => $this->nullableText($attributes['name_en'] ?? null),
                'birthdate' => $this->nullableText($attributes['birthdate'] ?? null),
                'email' => $email,
                'pid' => $cid,
                'thaid_pid' => $this->nullableText($attributes['thaid_pid'] ?? null),
                'cid_hash' => $hash,
                'provider_id' => $this->nullableText($attributes['provider_id'] ?? null),
                'account_id' => $this->nullableText($attributes['account_id'] ?? null),
                'hcode' => $this->nullableText($attributes['hcode'] ?? null),
                'hname' => $this->nullableText($attributes['hname'] ?? null),
                'hospcode' => $this->nullableText($attributes['hospcode'] ?? null),
                'last_login_at' => now(),
            ], static fn ($value) => $value !== null);

            // An explicitly supplied address is authoritative, including an empty value.
            // Providers that do not supply this field keep the existing address.
            if (array_key_exists('address', $attributes)) {
                $data['address'] = $this->nullableText($attributes['address']);
            }

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    ...$data,
                    'password' => Hash::make(Str::random(64)),
                    'email_verified_at' => null,
                ])->save();
            } else {
                $user->forceFill($data)->save();
            }

            return $user;
        });
    }

    private function belongsToCid(User $user, string $cid, string $hash): bool
    {
        foreach (['pid', 'thaid_pid'] as $field) {
            if (filled($user->{$field}) && $user->{$field} !== $cid) {
                return false;
            }
        }

        return ! filled($user->cid_hash)
            || $user->cid_hash === $hash
            || str_starts_with($user->cid_hash, 'user:');
    }

    private function availableEmail(string $email, ?int $currentUserId, ?string $existingEmail = null): string
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $usedByAnotherUser = User::query()->where('email', $email)
                ->when($currentUserId !== null, fn ($query) => $query->where('id', '!=', $currentUserId))
                ->exists();
            if (! $usedByAnotherUser) {
                return $email;
            }
        }

        return filled($existingEmail) ? $existingEmail : 'sso-'.Str::uuid().'@local.invalid';
    }

    private function personName(object $person): string
    {
        return trim(implode(' ', array_filter([
            $person->pname ?? null,
            $person->fname ?? null,
            $person->lname ?? null,
        ])));
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
