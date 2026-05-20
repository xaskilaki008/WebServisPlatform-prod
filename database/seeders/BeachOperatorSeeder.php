<?php

namespace Database\Seeders;

use App\Models\BeachOperator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BeachOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            [
                'beach_id' => 2,
                'login' => 'operator2',
                'password' => 'operator2-password',
                'last_name' => 'Иванов',
                'first_name' => 'Иван',
                'middle_name' => 'Иванович',
                'work_phone' => '79780000002',
            ],
            [
                'beach_id' => 3,
                'login' => 'operator3',
                'password' => 'operator3-password',
                'last_name' => 'Петров',
                'first_name' => 'Петр',
                'middle_name' => 'Петрович',
                'work_phone' => '79780000003',
            ],
        ];

        foreach ($operators as $operatorData) {
            $operator = BeachOperator::query()->firstOrNew([
                'login' => $operatorData['login'],
            ]);

            $operator->fill([
                'beach_id' => $operatorData['beach_id'],
                'password' => Hash::make($operatorData['password']),
                'operator_hash' => $operator->operator_hash ?: Str::random(64),
                'name' => "{$operatorData['last_name']} {$operatorData['first_name']} {$operatorData['middle_name']}",
                'last_name' => $operatorData['last_name'],
                'first_name' => $operatorData['first_name'],
                'middle_name' => $operatorData['middle_name'],
                'work_phone' => $operatorData['work_phone'],
            ]);

            $operator->save();
        }
    }
}
