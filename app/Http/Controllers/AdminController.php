<?php

namespace App\Http\Controllers;

use App\Models\FavoriteBeach;
use App\Models\Reaction;
use App\Models\WaveForecast;
use App\Services\AdminAuthService;
use App\Services\BrowserLoginThrottle;
use App\Services\WaveFetchService;
use Illuminate\Http\Request;
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

        return view('admin.index', [
            'parsingEnabled' => Cache::get('parsing_enabled', true),
            'fetchStatus' => $waveFetchService->status(),
            'forecastCount24h' => WaveForecast::query()
                ->where('parsed_at', '>=', now()->subDay())
                ->count(),
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

        return redirect('/admin')->with('status', $newStatus
            ? 'Плановый DWD-парсинг включён.'
            : 'Плановый DWD-парсинг выключен.');
    }

    public function forceFetch(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        $result = $waveFetchService->start();

        return redirect('/admin')->with('status', $result['message']);
    }

    public function forceFetchStatus(Request $request, AdminAuthService $auth, WaveFetchService $waveFetchService)
    {
        $this->authorizeAdmin($request, $auth);

        return response()->json($waveFetchService->status());
    }

    private function authorizeAdmin(Request $request, AdminAuthService $auth): void
    {
        abort_unless($auth->admin($request), 403);
    }

    private function blockedMessage(int $retryAfterSeconds): string
    {
        return "Слишком много попыток входа. Попробуйте через {$retryAfterSeconds} сек.";
    }
}
