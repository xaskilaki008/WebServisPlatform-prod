<?php

namespace App\Http\Controllers;

use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\BeachOperatorLog;
use App\Models\FavoriteBeach;
use App\Models\Operator;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActionLog;
use App\Models\WaveForecast;
use App\Services\AdminAuthService;
use App\Services\BrowserLoginThrottle;
use App\Services\UserActionLogger;
use App\Services\WaveFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function loginForm(Request $request, AdminAuthService $auth)
    {
        if ($auth->admin($request)) {
            return redirect('/admin');
        }

        return view('admin.login');
    }

    public function login(Request $request, AdminAuthService $auth, BrowserLoginThrottle $throttle)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleStatus = $throttle->status($request, 'admin');
        if ($throttleStatus['blocked']) {
            return back()
                ->withErrors(['login' => $this->blockedMessage($throttleStatus['retry_after_seconds'])])
                ->withInput(['login' => $validated['login']]);
        }

        if (!$auth->attempt($request, $validated['login'], $validated['password'])) {
            $throttleStatus = $throttle->registerFailure($request, 'admin');

            return back()
                ->withErrors(['login' => $throttleStatus['blocked']
                    ? $this->blockedMessage($throttleStatus['retry_after_seconds'])
                    : 'Неверный логин или пароль.'])
                ->withInput(['login' => $validated['login']]);
        }

        $throttle->clear($request, 'admin');

        return redirect('/admin');
    }

    public function logout(Request $request, AdminAuthService $auth)
    {
        $auth->logout($request);

        return redirect('/admin/login');
    }

    public function index(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        if (!$auth->admin($request)) {
            return view('admin.authorize');
        }

        $forecastWindowStart = now('UTC')->startOfHour();
        $forecastWindowEnd = $forecastWindowStart->copy()->addDay();
        $latestModelRunAt = WaveForecast::query()->max('model_run_at');
        $forecastCount24h = $latestModelRunAt
            ? WaveForecast::query()
                ->where('model_run_at', $latestModelRunAt)
                ->where('forecast_time', '>=', $forecastWindowStart)
                ->where('forecast_time', '<', $forecastWindowEnd)
                ->count()
            : 0;

        return view('admin.index', $this->withAdminPanelData([
            'parsingEnabled' => Cache::get('parsing_enabled', true),
            'fetchStatus' => $waveFetchService->status(),
            'forecastCount24h' => $forecastCount24h,
            'forecastWindowStart' => $forecastWindowStart->toDateTimeString(),
            'forecastWindowEnd' => $forecastWindowEnd->toDateTimeString(),
            'latestModelRunAt' => $latestModelRunAt,
            'latestParsedAt' => WaveForecast::query()->max('parsed_at'),
            'reactionCount1h' => Reaction::query()
                ->where('created_at', '>=', now()->subHour())
                ->count(),
            'favoriteCount' => FavoriteBeach::query()->count(),
            'favoriteVisitorCount' => FavoriteBeach::query()
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct()
                ->count('user_id'),
        ]));
    }

    public function toggleParsing(Request $request, AdminAuthService $auth)
    {
        $this->authorizeAdmin($request, $auth);

        $newStatus = !Cache::get('parsing_enabled', true);
        Cache::put('parsing_enabled', $newStatus);

        $message = $newStatus
            ? 'Плановый forecast model-парсинг включён.'
            : 'Плановый forecast model-парсинг выключен.';

        return $this->adminActionResponse($request, true, 'success', $message, [
            'parsing_enabled' => $newStatus,
        ]);
    }

    public function forceFetch(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        $result = $waveFetchService->start();

        return $this->adminActionResponse(
            $request,
            in_array($result['status'] ?? null, ['queued', 'running', 'success'], true),
            $result['status'] ?? 'unknown',
            $result['message'] ?? 'Статус запуска forecast model неизвестен.',
            $waveFetchService->status(),
            ($result['status'] ?? null) === 'failed' ? [$result['message'] ?? 'Ошибка запуска forecast model.'] : []
        );
    }

    public function forceFetchStatus(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService): JsonResponse
    {
        $this->authorizeAdmin($request, $auth);

        $status = $waveFetchService->status();

        return $this->adminJsonResponse(
            true,
            $status['status'] ?? 'idle',
            $this->dwdStatusMessage($status),
            $status,
            array_values(array_filter([$status['error'] ?? null]))
        );
    }

    public function resetFetchLock(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        $result = $waveFetchService->resetStaleLock();

        return $this->adminActionResponse(
            $request,
            (bool) ($result['reset'] ?? false),
            ($result['reset'] ?? false) ? 'success' : 'blocked',
            $result['message'] ?? 'Состояние forecast model не изменено.',
            $waveFetchService->status(),
            ($result['reset'] ?? false) ? [] : [$result['message'] ?? 'Сброс недоступен.']
        );
    }

    public function diagnoseDwd(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        $exitCode = Artisan::call('wave:diagnose');
        $status = $waveFetchService->status();
        $message = $exitCode === 0
            ? 'Диагностика forecast model завершена: критических проблем не найдено.'
            : 'Диагностика forecast model завершена: найдены проблемы.';

        return $this->adminActionResponse(
            $request,
            $exitCode === 0,
            $exitCode === 0 ? 'success' : 'error',
            $message,
            $status,
            $exitCode === 0 ? [] : [$message]
        );
    }

    public function dwdLog(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService): JsonResponse
    {
        $this->authorizeAdmin($request, $auth);

        return $this->adminJsonResponse(
            true,
            'success',
            'forecast model-лог прочитан.',
            ['last_log_lines' => $waveFetchService->logLines()]
        );
    }

    public function clearDwdLog(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        $result = $waveFetchService->clearLog();

        return $this->adminActionResponse(
            $request,
            (bool) ($result['cleared'] ?? false),
            ($result['cleared'] ?? false) ? 'success' : 'error',
            $result['message'] ?? 'forecast model-лог не очищен.',
            $waveFetchService->status(),
            ($result['cleared'] ?? false) ? [] : [$result['message'] ?? 'forecast model-лог не очищен.']
        );
    }

    public function users(Request $request, AdminAuthService $auth)
    {
        $admin = $this->authorizeAdmin($request, $auth);

        return view('admin.users', $this->withAdminPanelData([
            'admin' => $admin,
            'users' => User::query()
                ->with(['role', 'operator.beaches'])
                ->orderBy('id')
                ->paginate(30),
            'roles' => Role::query()->orderBy('name')->get(),
            'activeAdminCount' => $this->activeAdminCount(),
        ]));
    }

    public function updateUserRole(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        User $user
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $targetRole = Role::query()->where('name', $validated['role'])->firstOrFail();

        if ($admin->id === $user->id && $user->role_id !== $targetRole->id) {
            return back()->with('error', 'Нельзя менять собственную роль.');
        }

        if ($this->wouldRemoveLastActiveAdmin($user, $targetRole->name, (bool) $user->is_active)) {
            return back()->with('error', 'Нельзя снять роль или доступ у последнего активного администратора.');
        }

        $previousRole = $user->role?->name;
        $user->forceFill(['role_id' => $targetRole->id])->save();

        if ($targetRole->name === Role::OPERATOR) {
            Operator::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['work_phone' => null, 'created_at' => now()]
            );
        } elseif ($user->operator) {
            $user->operator()->delete();
        }

        $logger->log(
            $request,
            'admin_user_role_updated',
            $admin,
            User::class,
            $user->id,
            "Role changed from {$previousRole} to {$targetRole->name}"
        );

        return back()->with('status', 'Роль пользователя обновлена.');
    }

    public function updateUserActive(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        User $user
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $validated['is_active'];

        if ($admin->id === $user->id && !$isActive) {
            return back()->with('error', 'Нельзя отключить собственный аккаунт.');
        }

        if ($this->wouldRemoveLastActiveAdmin($user, $user->role?->name, $isActive)) {
            return back()->with('error', 'Нельзя снять роль или доступ у последнего активного администратора.');
        }

        $user->forceFill(['is_active' => $isActive])->save();

        $logger->log(
            $request,
            $isActive ? 'admin_user_activated' : 'admin_user_deactivated',
            $admin,
            User::class,
            $user->id,
            $isActive ? 'User activated by administrator' : 'User deactivated by administrator'
        );

        return back()->with('status', 'Статус пользователя обновлён.');
    }

    public function banUser(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        User $user
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        if ($admin->id === $user->id) {
            return back()->with('error', 'Нельзя забанить собственный аккаунт.');
        }

        if ($this->wouldRemoveLastActiveAdmin($user, $user->role?->name, false)) {
            return back()->with('error', 'Нельзя отключить последнего активного администратора.');
        }

        $user->forceFill(['is_active' => false])->save();

        $logger->log(
            $request,
            'admin_user_banned',
            $admin,
            User::class,
            $user->id,
            'User banned after suspicious activity warning.'
        );

        return back()->with('status', 'Пользователь забанен.');
    }

    public function operators(Request $request, AdminAuthService $auth)
    {
        $admin = $this->authorizeAdmin($request, $auth);
        $operatorRole = Role::query()->where('name', Role::OPERATOR)->firstOrFail();

        return view('admin.operators', $this->withAdminPanelData([
            'admin' => $admin,
            'users' => User::query()
                ->with(['role', 'operator.beaches'])
                ->where(function ($query) use ($operatorRole) {
                    $query->where('role_id', $operatorRole->id)
                        ->orWhereHas('operator');
                })
                ->orderBy('id')
                ->paginate(30),
            'candidateUsers' => User::query()
                ->with('role')
                ->where('id', '!=', $admin->id)
                ->whereDoesntHave('role', fn ($query) => $query->whereIn('name', [Role::ADMIN, Role::OPERATOR]))
                ->orderBy('login')
                ->get(),
            'beaches' => Beach::query()->orderBy('name')->get(),
        ]));
    }

    public function updateOperator(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        User $user
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        if ($admin->id === $user->id) {
            return back()->with('error', 'Нельзя назначить себя оператором через эту форму.');
        }

        if ($this->wouldRemoveLastActiveAdmin($user, Role::OPERATOR, true)) {
            return back()->with('error', 'Нельзя снять роль или доступ у последнего активного администратора.');
        }

        $validated = $request->validate([
            'work_phone' => ['nullable', 'string', 'max:32'],
            'beach_ids' => ['required', 'array', 'min:1'],
            'beach_ids.*' => ['integer', Rule::exists('beaches', 'id')],
        ]);

        $operatorRole = Role::query()->where('name', Role::OPERATOR)->firstOrFail();
        $previousRole = $user->role?->name;

        $user->forceFill([
            'role_id' => $operatorRole->id,
            'is_active' => true,
        ])->save();

        $operator = Operator::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'work_phone' => $validated['work_phone'] ?? null,
                'created_at' => $user->operator?->created_at ?? now(),
            ]
        );
        $operator->beaches()->sync($validated['beach_ids']);

        $logger->log(
            $request,
            'admin_operator_updated',
            $admin,
            Operator::class,
            $operator->id,
            'Operator profile updated; previous role: ' . ($previousRole ?? 'none')
        );

        return back()->with('status', 'Профиль оператора и закрепления обновлены.');
    }

    public function beaches(Request $request, AdminAuthService $auth)
    {
        $this->authorizeAdmin($request, $auth);

        return view('admin.beaches', $this->withAdminPanelData([
            'beaches' => Beach::query()
                ->orderBy('name')
                ->paginate(50),
            'assignments' => Operator::query()
                ->with(['user', 'beaches'])
                ->get(),
        ]));
    }

    public function actionLogs(Request $request, AdminAuthService $auth)
    {
        $this->authorizeAdmin($request, $auth);

        return view('admin.action-logs', $this->withAdminPanelData([
            'logs' => UserActionLog::query()
                ->with('user.role')
                ->latest('created_at')
                ->paginate(50),
            'operators' => Operator::query()
                ->with(['user.role', 'beaches'])
                ->orderBy('id')
                ->get(),
        ]));
    }

    public function manualCorrection(Request $request, AdminAuthService $auth)
    {
        $this->authorizeAdmin($request, $auth);

        return view('admin.manual-correction', $this->withAdminPanelData([
            'beaches' => Beach::query()
                ->orderBy('name')
                ->paginate(50),
        ]));
    }

    public function updateManualCorrection(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        Beach $beach
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        $validated = $request->validate([
            'operator_status' => ['required', 'in:0,1,2,3,4,5,6,hazard'],
            'operator_warning' => ['nullable', 'string', 'max:250'],
            'operator_wave_direction' => ['required', 'in:direct,left,right,azimuth,chaotic'],
            'operator_wave_azimuth' => ['nullable', 'required_if:operator_wave_direction,azimuth', 'integer', 'between:0,360'],
            'operator_wave_period' => ['required', 'integer', 'between:2,12'],
            'operator_access_status' => ['required', 'in:open,limited,closed'],
            'operator_validity' => ['required', 'in:30m,1h,3h,24h,until_disabled'],
        ]);

        $submittedAt = now();
        $expiresAt = match ($validated['operator_validity']) {
            '30m' => $submittedAt->copy()->addMinutes(30),
            '3h' => $submittedAt->copy()->addHours(3),
            '24h' => $submittedAt->copy()->addDay(),
            'until_disabled' => $submittedAt->copy()->addMonth(),
            default => $submittedAt->copy()->addHour(),
        };

        $beach->update([
            'operator_status' => $validated['operator_status'],
            'operator_warning' => $validated['operator_warning'] ?? null,
            'operator_wave_direction' => $validated['operator_wave_direction'],
            'operator_wave_azimuth' => $validated['operator_wave_direction'] === 'azimuth'
                ? ($validated['operator_wave_azimuth'] ?? null)
                : null,
            'operator_wave_period' => $validated['operator_wave_period'],
            'operator_access_status' => $validated['operator_access_status'],
            'operator_updated_at' => $submittedAt,
            'operator_expires_at' => $expiresAt,
        ]);

        $compatibilityOperator = $this->compatibilityAdminOperatorForBeach($admin, $beach);

        BeachOperatorLog::query()->create([
            'beach_operator_id' => $compatibilityOperator->id,
            'beach_id' => $beach->id,
            'submitted_at' => $submittedAt,
            'expires_at' => $expiresAt,
            'operator_status' => $validated['operator_status'],
            'operator_warning' => $validated['operator_warning'] ?? null,
            'operator_wave_direction' => $validated['operator_wave_direction'],
            'operator_wave_azimuth' => $validated['operator_wave_direction'] === 'azimuth'
                ? ($validated['operator_wave_azimuth'] ?? null)
                : null,
            'operator_wave_period' => $validated['operator_wave_period'],
            'operator_access_status' => $validated['operator_access_status'],
        ]);

        $logger->log(
            $request,
            'admin_manual_correction_updated',
            $admin,
            Beach::class,
            $beach->id,
            'Administrator updated manual beach correction.'
        );

        return back()->with('status', 'Ручная корректировка сохранена.');
    }

    public function resetManualCorrection(
        Request $request,
        AdminAuthService $auth,
        UserActionLogger $logger,
        Beach $beach
    ) {
        $admin = $this->authorizeAdmin($request, $auth);

        $beach->update([
            'operator_status' => null,
            'operator_warning' => null,
            'operator_wave_direction' => null,
            'operator_wave_azimuth' => null,
            'operator_wave_period' => null,
            'operator_access_status' => null,
            'operator_updated_at' => null,
            'operator_expires_at' => null,
        ]);

        $logger->log(
            $request,
            'admin_manual_correction_reset',
            $admin,
            Beach::class,
            $beach->id,
            'Administrator reset manual beach correction.'
        );

        return back()->with('status', 'Ручная корректировка сброшена.');
    }

    private function authorizeAdmin(Request $request, AdminAuthService $auth): User
    {
        $admin = $auth->admin($request);

        abort_unless($admin, 403);

        return $admin;
    }

    private function adminActionResponse(
        Request $request,
        bool $success,
        string $status,
        string $message,
        array $details = [],
        array $errors = []
    ) {
        if ($request->expectsJson()) {
            return $this->adminJsonResponse($success, $status, $message, $details, $errors);
        }

        return redirect('/admin')->with($success ? 'status' : 'error', $message);
    }

    private function adminJsonResponse(
        bool $success,
        string $status,
        string $message,
        array $details = [],
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'errors' => $errors,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    private function dwdStatusMessage(array $status): string
    {
        if (!empty($status['stale'])) {
            if (($status['status'] ?? null) === 'queued') {
                return 'Задача forecast model поставлена в очередь, но worker ещё не начал выполнение. Проверьте queue service.';
            }

            return 'Загрузка forecast model выглядит зависшей.';
        }

        if (!empty($status['queued'])) {
            return 'Задача forecast model поставлена в очередь и ожидает queue worker.';
        }

        if (!empty($status['running'])) {
            return 'Загрузка forecast model выполняется.';
        }

        if (($status['status'] ?? null) === 'success') {
            return 'Последняя загрузка forecast model завершена успешно.';
        }

        if (($status['status'] ?? null) === 'failed') {
            return 'Последняя загрузка forecast model завершилась ошибкой.';
        }

        return 'forecast model-загрузка сейчас не выполняется.';
    }

    private function blockedMessage(int $retryAfterSeconds): string
    {
        return "Слишком много попыток входа. Попробуйте через {$retryAfterSeconds} сек.";
    }

    private function withAdminPanelData(array $data = []): array
    {
        return $data + [
            'suspiciousUsers' => $this->suspiciousUsers(),
        ];
    }

    private function suspiciousUsers()
    {
        return User::query()
            ->with('role')
            ->withCount([
                'actionLogs as recent_action_count' => fn ($query) => $query
                    ->where('created_at', '>=', now()->subHour()),
            ])
            ->whereHas(
                'actionLogs',
                fn ($query) => $query->where('created_at', '>=', now()->subHour()),
                '>=',
                50
            )
            ->orderByDesc('recent_action_count')
            ->limit(10)
            ->get();
    }

    private function compatibilityAdminOperatorForBeach(User $admin, Beach $beach): BeachOperator
    {
        return BeachOperator::query()->firstOrCreate(
            ['login' => "admin-{$admin->id}-beach-{$beach->id}"],
            [
                'beach_id' => $beach->id,
                'password' => Hash::make(Str::random(40)),
                'operator_hash' => Str::random(64),
                'name' => $admin->full_name ?: $admin->name,
                'last_name' => $admin->last_name ?: '',
                'first_name' => $admin->first_name ?: '',
                'middle_name' => $admin->middle_name ?: '',
                'work_phone' => '00000000000',
            ]
        );
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->count();
    }

    private function wouldRemoveLastActiveAdmin(User $user, ?string $targetRoleName, bool $targetIsActive): bool
    {
        if (!$user->is_active || !$user->hasRole(Role::ADMIN)) {
            return false;
        }

        if ($targetRoleName === Role::ADMIN && $targetIsActive) {
            return false;
        }

        return $this->activeAdminCount() <= 1;
    }
}
