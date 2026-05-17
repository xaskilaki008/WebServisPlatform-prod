<?php

namespace Database\Seeders;

use App\Models\BeachOperator;
use Illuminate\Database\Seeder;

class BeachOperatorSeeder extends Seeder
{
    public function run(): void
    {
        BeachOperator::query()->updateOrCreate(
            ['operator_hash' => 'hash2'],
            ['beach_id' => 2, 'name' => 'Оператор пляжа 2']
        );

        BeachOperator::query()->updateOrCreate(
            ['operator_hash' => 'hash3'],
            ['beach_id' => 3, 'name' => 'Оператор пляжа 3']
        );
    }
}
