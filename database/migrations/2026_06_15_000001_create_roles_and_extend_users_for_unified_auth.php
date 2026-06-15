<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'user' => 'Regular registered user',
            'operator' => 'Beach operator',
            'admin' => 'Administrator',
        ] as $name => $description) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                [
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('roles')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'login')) {
                $table->string('login')->nullable()->unique()->after('role_id');
            }

            if (!Schema::hasColumn('users', 'nickname_key')) {
                $table->string('nickname_key')->nullable()->unique()->after('email');
            }

            if (!Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('password_hash');
            }

            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('full_name');
            }

            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('last_name');
            }

            if (!Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('middle_name');
            }
        });

        $userRoleId = DB::table('roles')->where('name', 'user')->value('id');

        DB::table('users')
            ->whereNull('role_id')
            ->update(['role_id' => $userRoleId]);

        DB::table('users')
            ->whereNull('password_hash')
            ->whereNotNull('password')
            ->update(['password_hash' => DB::raw('password')]);

        DB::table('users')
            ->whereNull('is_active')
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'is_active',
                'middle_name',
                'first_name',
                'last_name',
                'full_name',
                'password_hash',
                'nickname_key',
                'login',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropConstrainedForeignId('role_id');
            }
        });

        Schema::dropIfExists('roles');
    }
};
