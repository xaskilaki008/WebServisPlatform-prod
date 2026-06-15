<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthService
{
    public function __construct(private readonly UserAuthService $auth)
    {
    }

    public function admin(Request $request): ?User
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->is_active || !$user->hasRole(Role::ADMIN)) {
            return null;
        }

        return $user;
    }

    public function attempt(Request $request, string $login, string $password): ?User
    {
        $result = $this->auth->attempt($request, $login, $password);

        if (!$result['success']) {
            return null;
        }

        $user = $result['user'];

        if (!$user instanceof User || !$user->hasRole(Role::ADMIN) || !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        return $user;
    }

    public function logout(Request $request): void
    {
        $this->auth->logout($request);
    }
}
