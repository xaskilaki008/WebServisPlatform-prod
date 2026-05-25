<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            if (!Schema::hasColumn('visitors', 'visitor_hash')) {
                $table->string('visitor_hash')->nullable()->after('id');
            }
        });

        $this->makeVisitorLegacyFieldsNullable();

        Schema::table('visitors', function (Blueprint $table) {
            $table->unique('visitor_hash', 'visitors_visitor_hash_unique');
        });

        Schema::table('reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('reactions', 'visitor_id')) {
                $table->foreignId('visitor_id')
                    ->nullable()
                    ->after('beach_id')
                    ->constrained('visitors')
                    ->nullOnDelete();
            }
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->index(['beach_id', 'created_at'], 'reactions_beach_created_idx');
            $table->index(['visitor_id', 'beach_id', 'created_at'], 'reactions_visitor_beach_created_idx');
        });

        Schema::create('favorite_beaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->foreignId('beach_id')->constrained('beaches')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['visitor_id', 'beach_id'], 'favorite_beaches_visitor_beach_unique');
            $table->index('visitor_id', 'favorite_beaches_visitor_idx');
            $table->index('beach_id', 'favorite_beaches_beach_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_beaches');

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropIndex('reactions_beach_created_idx');
            $table->dropIndex('reactions_visitor_beach_created_idx');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropUnique('visitors_visitor_hash_unique');

            if (Schema::hasColumn('visitors', 'visitor_hash')) {
                $table->dropColumn('visitor_hash');
            }
        });
    }

    private function makeVisitorLegacyFieldsNullable(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['nickname', 'email', 'password'] as $column) {
                if (Schema::hasColumn('visitors', $column)) {
                    DB::statement("ALTER TABLE visitors ALTER COLUMN {$column} DROP NOT NULL");
                }
            }
        }
    }
};
