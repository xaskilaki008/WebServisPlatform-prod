<?php

namespace App\Http\Controllers;

use App\Models\FavoriteBeach;
use App\Models\Reaction;
use App\Models\WaveForecast;
use App\Services\AdminAuthService;
use App\Services\BrowserLoginThrottle;
use App\Services\WaveFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
        $this->authorizeAdmin($request, $auth);

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

        return view('admin.index', [
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
                ->select('visitor_id')
                ->distinct()
                ->count('visitor_id'),
        ]);
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

    private function authorizeAdmin(Request $request, AdminAuthService $auth): void
    {
        abort_unless($auth->admin($request), 403);
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
}
