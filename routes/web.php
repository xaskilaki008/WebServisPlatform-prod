<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\BeachController;
use App\Http\Controllers\Api\BeachInteractionController;
use App\Http\Controllers\Auth\UnifiedAuthController;
use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\BeachOperatorLog;
use App\Services\OperatorAccessService;
use App\Services\UserActionLogger;
use App\Services\WaveForecastSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, OperatorAccessService $operatorAccess) {
    $user = $request->user();
    $operatorBeachIds = $operatorAccess->currentOperatorBeachIds($request);
    $operatorBeachId = $operatorBeachIds[0] ?? null;
    $isAdmin = $user?->isAdmin() && $user->is_active;
    $isOperator = !$isAdmin && !empty($operatorBeachIds);

    return view('map', [
        'isAdmin' => $isAdmin,
        'isOperator' => $isOperator,
        'operatorBeachId' => $operatorBeachId,
        'operatorBeachIds' => $operatorBeachIds,
        'operatorPanelUrl' => count($operatorBeachIds) === 1 && $operatorBeachId
            ? "/operator/{$operatorBeachId}"
            : '/operator',
        'adminPanelUrl' => '/admin',
    ]);
});

Route::post('/api/auth/email/send-code', [UnifiedAuthController::class, 'sendCode']);
Route::post('/api/auth/password/send-code', [UnifiedAuthController::class, 'sendPasswordResetCode']);
Route::post('/api/auth/password/reset', [UnifiedAuthController::class, 'resetPassword']);
Route::post('/api/auth/register', [UnifiedAuthController::class, 'register']);
Route::post('/api/auth/login', [UnifiedAuthController::class, 'login']);
Route::post('/api/auth/logout', [UnifiedAuthController::class, 'logout']);

Route::post('/api/auth/operator/password', function (Request $request, OperatorAccessService $operatorAccess) {
    $validated = $request->validate([
        'current_password' => ['required', 'string'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $user = $request->user();

    if ($user && $user->isOperator()) {
        $hash = $user->password_hash ?: $user->password;

        if (!$hash || !Hash::check($validated['current_password'], $hash)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'password_hash' => $validated['password'],
        ])->save();

        return response()->json(['message' => 'Password changed']);
    }

    $legacy = $operatorAccess->currentLegacyOperator($request);

    abort_unless($legacy, 403);

    if (!Hash::check($validated['current_password'], $legacy->password)) {
        return response()->json(['message' => 'Current password is incorrect'], 422);
    }

    $legacy->update([
        'password' => Hash::make($validated['password']),
    ]);

    return response()->json(['message' => 'Password changed']);
});

Route::get('/operator', function (Request $request, OperatorAccessService $operatorAccess) {
    $operator = $operatorAccess->currentUnifiedOperator($request);

    if (!$operator) {
        $legacyBeachId = $operatorAccess->currentOperatorBeachId($request);

        abort_unless($legacyBeachId, 403, 'Доступ запрещен');

        return redirect("/operator/{$legacyBeachId}");
    }

    $beaches = $operator->beaches()->orderBy('name')->get();

    if ($beaches->count() === 1) {
        return redirect('/operator/' . $beaches->first()->id);
    }

    return view('operator-beaches', [
        'operator' => $operator,
        'beaches' => $beaches,
    ]);
});

Route::get('/operator/history', function (Request $request, OperatorAccessService $operatorAccess) {
    $user = $request->user();
    $operator = $operatorAccess->currentUnifiedOperator($request);
    $legacy = $operatorAccess->currentLegacyOperator($request);
    $isAllowedAdmin = $user?->isAdmin() && $user->is_active;
    $operatorIds = collect();

    abort_unless($legacy || $operator || $isAllowedAdmin, 403, 'Доступ запрещен');

    if ($legacy) {
        $operatorIds = collect([(int) $legacy->id]);
    } elseif ($operator && $user) {
        $beachIds = $operator->beaches()
            ->pluck('beaches.id')
            ->map(fn ($id) => (int) $id);

        $compatibilityLogins = $beachIds
            ->map(fn ($beachId) => "operator-{$user->id}-beach-{$beachId}")
            ->values();

        $operatorIds = BeachOperator::query()
            ->whereIn('beach_id', $beachIds)
            ->where(function ($query) use ($compatibilityLogins, $user) {
                $query->whereIn('login', $compatibilityLogins)
                    ->orWhere('login', $user->login);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    } elseif ($isAllowedAdmin) {
        $operatorIds = BeachOperator::query()
            ->where('login', 'like', "admin-{$user->id}-beach-%")
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    $logs = BeachOperatorLog::query()
        ->with(['beach', 'operator'])
        ->whereIn('beach_operator_id', $operatorIds)
        ->orderByDesc('submitted_at')
        ->limit(100)
        ->get();

    return view('operator-history', [
        'logs' => $logs,
        'operator' => $operator,
    ]);
});

Route::get('/operator/{id}', function (Request $request, WaveForecastSelector $forecastSelector, OperatorAccessService $operatorAccess, int $id) {
    $operator = $operatorAccess->compatibilityOperatorForBeach($request, $id);

    abort_unless($operator, 403, 'Доступ запрещен');

    $beach = Beach::query()->findOrFail($id);
    $beach->setRelation('latestForecast', $forecastSelector->forBeach($id, now('UTC')));

    return view('operator', [
        'operator' => $operator,
        'beach' => $beach,
    ]);
});

Route::post('/operator/{id}', function (
    Request $request,
    OperatorAccessService $operatorAccess,
    UserActionLogger $logger,
    int $id
) {
    $operator = $operatorAccess->compatibilityOperatorForBeach($request, $id);

    abort_unless($operator, 403, 'Доступ запрещен');

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

    $beach = Beach::query()->findOrFail($id);
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

    BeachOperatorLog::query()->create([
        'beach_operator_id' => $operator->id,
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
        'operator_record_created',
        $request->user(),
        Beach::class,
        $beach->id,
        'Operator submitted beach sea-state data.'
    );

    return redirect("/operator/{$id}")->with('status', 'Данные сохранены и опубликованы');
});

Route::get('/admin/login', [AdminController::class, 'loginForm']);
Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/admin/logout', [AdminController::class, 'logout']);
Route::get('/admin', [AdminController::class, 'index']);
Route::post('/admin/toggle-parsing', [AdminController::class, 'toggleParsing']);
Route::post('/admin/force-fetch', [AdminController::class, 'forceFetch']);
Route::post('/admin/force-fetch/reset-lock', [AdminController::class, 'resetFetchLock']);
Route::get('/admin/force-fetch/status', [AdminController::class, 'forceFetchStatus']);
Route::post('/admin/dwd-diagnose', [AdminController::class, 'diagnoseDwd']);
Route::get('/admin/dwd-log', [AdminController::class, 'dwdLog']);
Route::post('/admin/dwd-log/clear', [AdminController::class, 'clearDwdLog']);
Route::get('/admin/beaches', [AdminController::class, 'beaches']);
Route::get('/admin/operators', [AdminController::class, 'operators']);
Route::post('/admin/operators/{user}', [AdminController::class, 'updateOperator']);
Route::get('/admin/users', [AdminController::class, 'users']);
Route::post('/admin/users/{user}/role', [AdminController::class, 'updateUserRole']);
Route::post('/admin/users/{user}/active', [AdminController::class, 'updateUserActive']);
Route::post('/admin/users/{user}/ban', [AdminController::class, 'banUser']);
Route::get('/admin/action-logs', [AdminController::class, 'actionLogs']);
Route::get('/admin/manual-correction', [AdminController::class, 'manualCorrection']);
Route::post('/admin/manual-correction/{beach}', [AdminController::class, 'updateManualCorrection']);
Route::post('/admin/manual-correction/{beach}/reset', [AdminController::class, 'resetManualCorrection']);

Route::get('/api/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/api/beach-photo/{id}', [BeachController::class, 'getPhoto']);
Route::post('/api/beaches/{beach}/reaction', [BeachInteractionController::class, 'reaction']);
Route::get('/api/favorites', [BeachInteractionController::class, 'favorites']);
Route::post('/api/beaches/{beach}/favorite-toggle', [BeachInteractionController::class, 'favoriteToggle']);
