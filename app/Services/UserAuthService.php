<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

class UserAuthService
{
    public function __construct(
        private readonly UserIdentityNormalizer $normalizer,
        private readonly UserActionLogger $logger,
        private readonly UserRedirectService $redirects
    ) {
    }

    public function findByIdentifier(string $identifier): ?User
    {
        $normalized = $this->normalizer->identifier($identifier);

        $query = User::query()->with('role', 'operator.beaches');

        if (str_contains($normalized, '@')) {
            $query->where('email', $normalized);
        } else {
            $query->where(function ($query) use ($normalized) {
                $query->where('login', $normalized)
                    ->orWhere('nickname_key', $normalized);
            });
        }

        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function canLogin(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isRegularUser()) {
            return (bool) $user->email_verified_at;
        }

        if ($user->isOperator()) {
            return $user->operator !== null && $user->operator->beaches->isNotEmpty();
        }

        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function attempt(Request $request, string $identifier, string $password): array
    {
        $user = $this->findByIdentifier($identifier);

        if (!$user || !$this->passwordMatches($user, $password) || !$this->canLogin($user)) {
            $this->logger->log($request, 'login_failed', $user, 'users', $user?->id, 'Unified login failed.');

            return [
                'success' => false,
                'message' => 'Неверный логин или пароль.',
            ];
        }

        Auth::login($user);
        $request->session()->regenerate();
        Cookie::queue(Cookie::forget('operator_hash'));
        Cookie::queue(Cookie::forget('admin_auth'));
        $this->logger->log($request, 'login', $user, 'users', (int) $user->id, 'Unified login succeeded.');

        return [
            'success' => true,
            'user' => $user,
            'redirect_url' => $this->redirects->targetFor($user),
        ];
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->logger->log($request, 'logout', $user, 'users', (int) $user->id, 'User logged out.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget('operator_hash'));
        Cookie::queue(Cookie::forget('admin_auth'));
    }

    public function passwordMatches(User $user, string $password): bool
    {
        $hash = $user->password_hash ?: $user->password;

        return $hash && Hash::check($password, $hash);
    }

    public function roleName(User $user): ?string
    {
        return $user->role?->name;
    }

    public function isAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }
}
