<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\UserIdentityNormalizer;
use Illuminate\Console\Command;

class CreateAdministrator extends Command
{
    protected $signature = 'admin:create {login?} {--email=}';
    protected $description = 'Create or update an administrator account';

    public function handle(UserIdentityNormalizer $normalizer): int
    {
        $login = $normalizer->login($this->argument('login') ?: $this->ask('Admin login'));
        $email = $normalizer->email($this->option('email') ?: "{$login}@local.invalid");
        $password = $this->secret('Admin password');
        $passwordConfirmation = $this->secret('Confirm admin password');

        if (!$login || !$password || !$passwordConfirmation) {
            $this->error('Login and password are required.');

            return self::FAILURE;
        }

        if ($password !== $passwordConfirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        User::query()->updateOrCreate(
            ['login' => $login],
            [
                'role_id' => $role->id,
                'name' => $login,
                'email' => $email,
                'password' => $password,
                'password_hash' => $password,
                'full_name' => $login,
                'is_active' => true,
            ]
        );

        $this->info("Administrator '{$login}' has been saved.");

        return self::SUCCESS;
    }
}
