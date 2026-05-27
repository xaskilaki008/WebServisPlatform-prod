<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <p class="admin-kicker">DWD parser</p>
                    <h1>Панель администратора</h1>
                </div>
                <form method="POST" action="/admin/logout">
                    @csrf
                    <button type="submit" class="action-button secondary">Выйти</button>
                </form>
            </header>

            @if(session('status'))
                <div class="admin-flash">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="admin-flash error">{{ session('error') }}</div>
            @endif

            <div class="admin-grid">
                <section class="admin-section">
                    <h2>DWD-парсинг</h2>
                    <div class="admin-status-row">
                        <span>Плановый парсинг</span>
                        <strong>{{ $parsingEnabled ? 'Включён' : 'Выключен' }}</strong>
                    </div>
                    <p class="admin-note">Включение парсинга не запускает загрузку сразу, а только разрешает следующий плановый запуск.</p>
                    <div class="admin-actions">
                        <form method="POST" action="/admin/toggle-parsing">
                            @csrf
                            <button type="submit" class="action-button secondary">
                                {{ $parsingEnabled ? 'Выключить парсинг' : 'Включить парсинг' }}
                            </button>
                        </form>
                        <form method="POST" action="/admin/force-fetch">
                            @csrf
                            <button type="submit" class="action-button primary" @disabled(($fetchStatus['running'] ?? false) && !($fetchStatus['stale'] ?? false))>
                                {{ ($fetchStatus['running'] ?? false) && !($fetchStatus['stale'] ?? false) ? 'Загрузка выполняется' : 'Загрузить DWD сейчас' }}
                            </button>
                        </form>
                        <form method="POST" action="/admin/dwd-diagnose">
                            @csrf
                            <button type="submit" class="action-button secondary">Проверить DWD</button>
                        </form>
                        @if($fetchStatus['can_reset'] ?? false)
                            <form method="POST" action="/admin/force-fetch/reset-lock">
                                @csrf
                                <button type="submit" class="action-button secondary">Сбросить состояние DWD</button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="admin-section">
                    <h2>Последняя операция</h2>
                    <div class="admin-status-row"><span>Статус</span><strong>{{ $fetchStatus['status'] ?? 'idle' }}</strong></div>
                    <div class="admin-status-row"><span>Этап</span><strong>{{ $fetchStatus['stage'] ?? '-' }}</strong></div>
                    @if($fetchStatus['cache_file_conflict'] ?? false)
                        <div class="admin-flash error">Cache-lock и файловый статус DWD расходятся. Можно сбросить состояние и запустить DWD заново.</div>
                    @elseif($fetchStatus['stale'] ?? false)
                        <div class="admin-flash error">Загрузка выглядит зависшей. Можно сбросить lock и запустить DWD заново.</div>
                    @elseif($fetchStatus['running'] ?? false)
                        <div class="admin-flash">Загрузка DWD сейчас выполняется.</div>
                    @endif
                    @if(($fetchStatus['log_stale'] ?? false) && ($fetchStatus['running'] ?? false))
                        <div class="admin-flash error">Нет новых строк DWD-лога {{ $fetchStatus['minutes_since_last_log'] ?? '?' }} мин. Возможно, загрузка зависла.</div>
                    @endif
                    <div class="admin-status-row"><span>Старт</span><strong>{{ $fetchStatus['started_at'] ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Heartbeat</span><strong>{{ $fetchStatus['heartbeat_at'] ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Последняя строка лога</span><strong>{{ $fetchStatus['last_log_at'] ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Завершение</span><strong>{{ $fetchStatus['finished_at'] ?? '-' }}</strong></div>
                    @if(!empty($fetchStatus['last_log_lines']))
                        @php($lastDwdLogLine = collect($fetchStatus['last_log_lines'])->last())
                        <div class="admin-status-row">
                            <span>Последнее сообщение</span>
                            <strong>{{ $lastDwdLogLine ?: '-' }}</strong>
                        </div>
                    @endif
                    @if($fetchStatus['error'] ?? null)
                        <div class="admin-flash error">{{ $fetchStatus['error'] }}</div>
                    @endif
                </section>

                <section class="admin-section">
                    <h2>Диагностика DWD</h2>
                    @if(!empty($fetchStatus['diagnostic']))
                        <div class="admin-status-row">
                            <span>Проверено</span>
                            <strong>{{ $fetchStatus['diagnostic']['checked_at'] ?? '-' }}</strong>
                        </div>
                        <div class="admin-diagnostic-list">
                            @foreach(($fetchStatus['diagnostic']['results'] ?? []) as $check)
                                <?php
                                    $level = $check['level'] ?? (!empty($check['ok']) ? 'ok' : 'error');
                                    $label = ['ok' => 'OK', 'warning' => 'WARNING', 'error' => 'ERROR'][$level] ?? 'ERROR';
                                ?>
                                <div class="admin-diagnostic-item">
                                    <div class="admin-status-row">
                                        <span>{{ $check['name'] ?? '-' }}</span>
                                        <strong class="admin-check admin-check-{{ $level }}">{{ $label }}</strong>
                                    </div>
                                    <pre class="admin-diagnostic-message">{{ $check['message'] ?? '' }}</pre>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="admin-note">Диагностика ещё не запускалась.</p>
                    @endif
                </section>

                <section class="admin-section">
                    <div class="admin-section-title">
                        <h2>Последние строки DWD-лога</h2>
                        <form method="POST" action="/admin/dwd-log/clear">
                            @csrf
                            <button type="submit" class="action-button secondary">Очистить DWD-лог</button>
                        </form>
                    </div>
                    @if(!empty($fetchStatus['last_log_lines']))
                        <pre class="admin-log">{{ implode(PHP_EOL, $fetchStatus['last_log_lines']) }}</pre>
                    @else
                        <p class="admin-note">Лог DWD пока пуст.</p>
                    @endif
                </section>

                <section class="admin-section">
                    <h2>Состояние системы</h2>
                    <div class="admin-status-row">
                        <span>Актуальных прогнозов на ближайшие 24 часа</span>
                        <strong>{{ $forecastCount24h }}</strong>
                    </div>
                    <p class="admin-note">Считаются записи последнего запуска модели по `forecast_time` в интервале [{{ $forecastWindowStart }}, {{ $forecastWindowEnd }}) UTC.</p>
                    <div class="admin-status-row"><span>Последний запуск модели</span><strong>{{ $latestModelRunAt ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Последний парсинг</span><strong>{{ $latestParsedAt ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Реакций за последний час</span><strong>{{ $reactionCount1h }}</strong></div>
                    <div class="admin-status-row"><span>Пляжей в избранном</span><strong>{{ $favoriteCount }}</strong></div>
                    <div class="admin-status-row"><span>Посетителей с избранным</span><strong>{{ $favoriteVisitorCount }}</strong></div>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
