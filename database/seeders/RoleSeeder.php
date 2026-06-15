<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            Role::USER => 'Regular registered user',
            Role::OPERATOR => 'Beach operator',
            Role::ADMIN => 'Administrator',
        ] as $name => $description) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }
    }
}
