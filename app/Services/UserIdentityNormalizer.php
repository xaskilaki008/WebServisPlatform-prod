<?php

namespace App\Services;

use Illuminate\Support\Str;

class UserIdentityNormalizer
{
    public function email(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function login(string $login): string
    {
        return Str::lower(trim($login));
    }

    public function nicknameKey(string $nickname): string
    {
        return Str::lower(trim($nickname));
    }

    public function identifier(string $identifier): string
    {
        return Str::lower(trim($identifier));
    }
}
