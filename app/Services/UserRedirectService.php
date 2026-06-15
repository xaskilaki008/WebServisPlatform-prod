<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class UserRedirectService
{
    public function targetFor(User $user): string
    {
        $user->loadMissing('role', 'operator.beaches');

        return match ($user->role?->name) {
            Role::ADMIN => '/admin',
            Role::OPERATOR => $this->operatorTarget($user),
            default => '/',
        };
    }

    private function operatorTarget(User $user): string
    {
        $beaches = $user->operator?->beaches ?? collect();

        if ($beaches->count() === 1) {
            return '/operator/' . $beaches->first()->id;
        }

        return '/operator';
    }
}
