<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reactions')) {
            $this->makeReactionVisitorNullable();

            Schema::table('reactions', function (Blueprint $table) {
                if (!Schema::hasColumn('reactions', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('visitor_id')
                        ->constrained('users')
                        ->nullOnDelete();

                    $table->index(['user_id', 'beach_id', 'created_at'], 'reactions_user_beach_created_idx');
                }
            });
        }

        if (Schema::hasTable('favorite_beaches')) {
            $this->makeFavoriteVisitorNullable();

            Schema::table('favorite_beaches', function (Blueprint $table) {
                if (!Schema::hasColumn('favorite_beaches', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('visitor_id')
                        ->constrained('users')
                        ->nullOnDelete();

                    $table->unique(['user_id', 'beach_id'], 'favorite_beaches_user_beach_unique');
                    $table->index('user_id', 'favorite_beaches_user_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('favorite_beaches') && Schema::hasColumn('favorite_beaches', 'user_id')) {
            Schema::table('favorite_beaches', function (Blueprint $table) {
                $table->dropUnique('favorite_beaches_user_beach_unique');
                $table->dropIndex('favorite_beaches_user_idx');
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasTable('reactions') && Schema::hasColumn('reactions', 'user_id')) {
            Schema::table('reactions', function (Blueprint $table) {
                $table->dropIndex('reactions_user_beach_created_idx');
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }

    private function makeFavoriteVisitorNullable(): void
    {
        if (!Schema::hasColumn('favorite_beaches', 'visitor_id')) {
            return;
        }

        $this->makeNullable('favorite_beaches', 'visitor_id');
    }

    private function makeReactionVisitorNullable(): void
    {
        if (!Schema::hasColumn('reactions', 'visitor_id')) {
            return;
        }

        $this->makeNullable('reactions', 'visitor_id');
    }

    private function makeNullable(string $table, string $column): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL"),
            'mysql', 'mariadb' => DB::statement("ALTER TABLE {$table} MODIFY {$column} BIGINT UNSIGNED NULL"),
            default => null,
        };
    }
};
