<?php

namespace App\Console\Commands;

use App\Models\Administrator;
use Illuminate\Console\Command;

class CreateAdministrator extends Command
{
    protected $signature = 'admin:create {login?}';
    protected $description = 'Create or update an administrator account';

    public function handle(): int
    {
        $login = $this->argument('login') ?: $this->ask('Admin login');
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

        Administrator::query()->updateOrCreate(
            ['login' => $login],
            ['password' => $password]
        );

        $this->info("Administrator '{$login}' has been saved.");

        return self::SUCCESS;
    }
}
