<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\UserEmailVerificationCodeMail;
use App\Mail\UserPasswordResetCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\Role;
use App\Models\User;
use App\Services\UserActionLogger;
use App\Services\UserAuthService;
use App\Services\UserIdentityNormalizer;
use App\Services\UserRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class UnifiedAuthController extends Controller
{
    public function __construct(
        private readonly UserIdentityNormalizer $normalizer,
        private readonly UserActionLogger $logger,
        private readonly UserAuthService $auth,
        private readonly UserRedirectService $redirects
    ) {
    }

    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $this->normalizer->email($validated['email']);
        $rateKey = $this->rateKey($request, 'email-code-send', $email);

        if ($this->tooManyAttempts($rateKey, 3)) {
            return $this->rateLimitResponse($rateKey);
        }

        $role = Role::query()->where('name', Role::USER)->firstOrFail();
        $existing = User::query()->where('email', $email)->first();

        if ($this->identifierConflictExists($email, $existing ?: new User())) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Этот email конфликтует с уже занятым логином или ником.',
            ], 422);
        }

        if ($existing && !$existing->isRegularUser()) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Этот email уже используется.',
            ], 422);
        }

        $user = $existing ?: User::query()->create([
            'role_id' => $role->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'password_hash' => Hash::make(Str::random(40)),
            'is_active' => true,
        ]);

        if ($user->email_verified_at) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Этот email уже подтверждён.',
            ], 422);
        }

        $code = (string) random_int(100000, 999999);
        $verificationCode = EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'purpose' => 'registration',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
        ]);

        $isLogMailer = config('mail.default') === 'log';

        try {
            Mail::to($email)->send(new UserEmailVerificationCodeMail($code));
        } catch (\Throwable $exception) {
            $verificationCode->delete();
            report($exception);
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить код подтверждения. Проверьте настройки почты MAIL_*.',
            ], 503);
        }

        RateLimiter::hit($rateKey, 60);
        $this->logger->log($request, 'email_code_sent', $user, 'users', (int) $user->id, 'Registration email code sent.');

        return response()->json([
            'success' => true,
            'message' => $isLogMailer
                ? 'Код подтверждения записан в storage/logs/laravel.log, потому что MAIL_MAILER=log.'
                : 'Код подтверждения отправлен на email. Он действует 10 минут.',
        ]);
    }

    public function sendPasswordResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $this->normalizer->email($validated['email']);
        $rateKey = $this->rateKey($request, 'password-reset-send', $email);

        if ($this->tooManyAttempts($rateKey, 3)) {
            return $this->rateLimitResponse($rateKey);
        }

        $user = User::query()->where('email', $email)->first();

        if (!$user || !$user->is_active || !$user->email_verified_at) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Активный пользователь с такой подтверждённой почтой не найден.',
            ], 422);
        }

        EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);
        $verificationCode = EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'purpose' => 'password_reset',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
        ]);

        $isLogMailer = config('mail.default') === 'log';

        try {
            Mail::to($email)->send(new UserPasswordResetCodeMail($code));
        } catch (\Throwable $exception) {
            $verificationCode->delete();
            report($exception);
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отправить код сброса пароля. Проверьте настройки почты MAIL_*.',
            ], 503);
        }

        RateLimiter::hit($rateKey, 60);
        $this->logger->log($request, 'password_reset_code_sent', $user, 'users', (int) $user->id, 'Password reset code sent.');

        return response()->json([
            'success' => true,
            'masked_email' => $this->maskEmail($email),
            'message' => $isLogMailer
                ? 'Код сброса пароля записан в storage/logs/laravel.log, потому что MAIL_MAILER=log.'
                : 'Код сброса пароля отправлен на email. Он действует 10 минут.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'verification_code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $this->normalizer->email($validated['email']);
        $rateKey = $this->rateKey($request, 'password-reset-check', $email);

        if ($this->tooManyAttempts($rateKey, 5)) {
            return $this->rateLimitResponse($rateKey);
        }

        $user = User::query()->where('email', $email)->first();

        if (!$user || !$user->is_active || !$user->email_verified_at) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Неверный или просроченный код сброса пароля.',
            ], 422);
        }

        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'password_reset')
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (
            !$verificationCode
            || $verificationCode->expires_at->isPast()
            || !Hash::check($validated['verification_code'], $verificationCode->code_hash)
        ) {
            if ($verificationCode) {
                $verificationCode->increment('attempts');
            }

            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Неверный или просроченный код сброса пароля.',
            ], 422);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'password_hash' => $validated['password'],
        ])->save();

        $verificationCode->update([
            'used_at' => now(),
        ]);

        RateLimiter::clear($rateKey);
        $this->logger->log($request, 'password_reset_completed', $user, 'users', (int) $user->id, 'User password reset completed.');

        return response()->json([
            'success' => true,
            'masked_email' => $this->maskEmail($email),
            'message' => 'Пароль изменён. Теперь можно войти с новым паролем.',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[\pL\pN_-]+$/u'],
            'email' => ['required', 'email', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'verification_code' => ['required', 'digits:6'],
        ]);

        $email = $this->normalizer->email($validated['email']);
        $nicknameKey = $this->normalizer->nicknameKey($validated['nickname']);
        $rateKey = $this->rateKey($request, 'email-code-check', $email);

        if ($this->tooManyAttempts($rateKey, 5)) {
            return $this->rateLimitResponse($rateKey);
        }

        $user = User::query()->with('role')->where('email', $email)->first();

        if (!$user || !$user->isRegularUser() || $user->email_verified_at) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Неверный или просроченный код подтверждения.',
            ], 422);
        }

        if ($this->identifierConflictExists($nicknameKey, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Этот ник или идентификатор уже занят.',
            ], 422);
        }

        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'registration')
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (
            !$verificationCode
            || $verificationCode->expires_at->isPast()
            || !Hash::check($validated['verification_code'], $verificationCode->code_hash)
        ) {
            if ($verificationCode) {
                $verificationCode->increment('attempts');
            }

            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Неверный или просроченный код подтверждения.',
            ], 422);
        }

        $fullName = trim(implode(' ', array_filter([
            $validated['last_name'] ?? null,
            $validated['first_name'] ?? null,
            $validated['middle_name'] ?? null,
        ]))) ?: $validated['nickname'];

        $user->forceFill([
            'login' => $nicknameKey,
            'nickname_key' => $nicknameKey,
            'name' => $fullName,
            'full_name' => $fullName,
            'last_name' => $validated['last_name'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'password' => $validated['password'],
            'password_hash' => $validated['password'],
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        $verificationCode->update([
            'used_at' => now(),
        ]);

        RateLimiter::clear($rateKey);
        $this->logger->log($request, 'register', $user, 'users', (int) $user->id, 'User registration completed.');
        $this->logger->log($request, 'email_verified', $user, 'users', (int) $user->id, 'Email verified during registration.');

        $this->auth->attempt($request, $email, $validated['password']);

        return response()->json([
            'success' => true,
            'message' => 'Регистрация завершена.',
            'redirect_url' => $this->redirects->targetFor($user->fresh(['role', 'operator.beaches'])),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $this->normalizer->identifier($validated['identifier']);
        $rateKey = $this->rateKey($request, 'login', $identifier);

        if ($this->tooManyAttempts($rateKey, 5)) {
            return $this->rateLimitResponse($rateKey);
        }

        $result = $this->auth->attempt($request, $identifier, $validated['password']);

        if (!$result['success']) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'success' => false,
                'message' => 'Неверный логин или пароль.',
            ], 403);
        }

        RateLimiter::clear($rateKey);

        return response()->json([
            'success' => true,
            'message' => 'Вход выполнен.',
            'redirect_url' => $result['redirect_url'],
            'role' => $result['user']->role?->name,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return response()->json([
            'success' => true,
            'redirect_url' => '/',
        ]);
    }

    private function identifierConflictExists(string $identifier, User $currentUser): bool
    {
        return User::query()
            ->where('id', '!=', $currentUser->id)
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('login', $identifier)
                    ->orWhere('nickname_key', $identifier);
            })
            ->exists();
    }

    private function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    private function rateLimitResponse(string $key): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Слишком много попыток. Попробуйте позже.',
            'retry_after_seconds' => RateLimiter::availableIn($key),
        ], 429);
    }

    private function rateKey(Request $request, string $scope, string $identifier): string
    {
        return "unified_auth:{$scope}:" . sha1($request->ip() . '|' . $identifier);
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $firstLetter = Str::substr($localPart, 0, 1) ?: '*';

        return "{$firstLetter}...@{$domain}";
    }
}
