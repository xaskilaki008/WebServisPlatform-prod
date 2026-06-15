<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $operatorRoleId = DB::table('roles')->where('name', 'operator')->value('id');

        if (!$adminRoleId || !$operatorRoleId) {
            throw new RuntimeException('Base roles must exist before migrating legacy auth accounts.');
        }

        if (Schema::hasTable('administrators')) {
            DB::table('administrators')->orderBy('id')->get()->each(function ($admin) use ($adminRoleId) {
                $login = $this->normalizeIdentifier($admin->login);
                $email = "legacy-admin-{$admin->id}@local.invalid";

                $this->assertIdentifierAvailable($login, 'admin', (int) $admin->id);

                DB::table('users')->updateOrInsert(
                    ['login' => $login],
                    [
                        'role_id' => $adminRoleId,
                        'name' => $admin->login,
                        'email' => $email,
                        'nickname_key' => null,
                        'password' => $admin->password,
                        'password_hash' => $admin->password,
                        'full_name' => $admin->login,
                        'last_name' => null,
                        'first_name' => null,
                        'middle_name' => null,
                        'is_active' => true,
                        'email_verified_at' => null,
                        'updated_at' => now(),
                        'created_at' => $admin->created_at ?? now(),
                    ]
                );
            });
        }

        if (Schema::hasTable('beach_operators')) {
            DB::table('beach_operators')->orderBy('id')->get()->each(function ($legacyOperator) use ($operatorRoleId) {
                $login = $this->normalizeIdentifier($legacyOperator->login ?? "operator{$legacyOperator->id}");
                $email = "legacy-operator-{$legacyOperator->id}@local.invalid";

                $this->assertIdentifierAvailable($login, 'operator', (int) $legacyOperator->id);

                DB::table('users')->updateOrInsert(
                    ['login' => $login],
                    [
                        'role_id' => $operatorRoleId,
                        'name' => $legacyOperator->name ?? $login,
                        'email' => $email,
                        'nickname_key' => null,
                        'password' => $legacyOperator->password,
                        'password_hash' => $legacyOperator->password,
                        'full_name' => $legacyOperator->name ?? $login,
                        'last_name' => $legacyOperator->last_name ?? null,
                        'first_name' => $legacyOperator->first_name ?? null,
                        'middle_name' => $legacyOperator->middle_name ?? null,
                        'is_active' => true,
                        'email_verified_at' => null,
                        'updated_at' => now(),
                        'created_at' => $legacyOperator->created_at ?? now(),
                    ]
                );

                $userId = DB::table('users')->where('login', $login)->value('id');

                DB::table('operators')->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'work_phone' => $legacyOperator->work_phone ?? null,
                        'created_at' => $legacyOperator->created_at ?? now(),
                    ]
                );

                $operatorId = DB::table('operators')->where('user_id', $userId)->value('id');

                if ($operatorId && $legacyOperator->beach_id) {
                    DB::table('operator_beach')->updateOrInsert(
                        [
                            'operator_id' => $operatorId,
                            'beach_id' => $legacyOperator->beach_id,
                        ],
                        [
                            'created_at' => $legacyOperator->created_at ?? now(),
                        ]
                    );
                }
            });
        }
    }

    public function down(): void
    {
        DB::table('operator_beach')->delete();
        DB::table('operators')->delete();
        DB::table('users')
            ->where(function ($query) {
                $query->where('email', 'like', 'legacy-admin-%@local.invalid')
                    ->orWhere('email', 'like', 'legacy-operator-%@local.invalid');
            })
            ->delete();
    }

    private function normalizeIdentifier(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function assertIdentifierAvailable(string $login, string $legacyType, int $legacyId): void
    {
        $conflict = DB::table('users')
            ->where(function ($query) use ($login) {
                $query->where('login', $login)
                    ->orWhere('nickname_key', $login)
                    ->orWhere('email', $login);
            })
            ->first();

        if (!$conflict) {
            return;
        }

        $expectedEmail = "legacy-{$legacyType}-{$legacyId}@local.invalid";

        if (($conflict->email ?? null) === $expectedEmail) {
            return;
        }

        throw new RuntimeException("Identifier '{$login}' conflicts with an existing user.");
    }
};
