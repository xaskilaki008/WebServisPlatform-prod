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
                            <button type="submit" class="action-button primary" @disabled($fetchStatus['running'])>
                                {{ $fetchStatus['running'] ? 'Загрузка выполняется' : 'Загрузить DWD сейчас' }}
                            </button>
                        </form>
                        @if($fetchStatus['can_reset'])
                            <form method="POST" action="/admin/force-fetch/reset-lock">
                                @csrf
                                <button type="submit" class="action-button secondary">
                                    Сбросить зависший запуск
                                </button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="admin-section">
                    <h2>Последняя операция</h2>
                    <div class="admin-status-row"><span>Статус</span><strong>{{ $fetchStatus['status'] }}</strong></div>
                    @if($fetchStatus['stale'])
                        <div class="admin-flash error">Загрузка выглядит зависшей. Можно сбросить lock и запустить DWD заново.</div>
                    @elseif($fetchStatus['running'])
                        <div class="admin-flash">Загрузка DWD сейчас выполняется.</div>
                    @endif
                    <div class="admin-status-row"><span>Старт</span><strong>{{ $fetchStatus['started_at'] ?? '-' }}</strong></div>
                    <div class="admin-status-row"><span>Завершение</span><strong>{{ $fetchStatus['finished_at'] ?? '-' }}</strong></div>
                    @if($fetchStatus['error'])
                        <div class="admin-flash error">{{ $fetchStatus['error'] }}</div>
                    @endif
                </section>

                <section class="admin-section">
                    <h2>Состояние системы</h2>
                    <div class="admin-status-row"><span>Прогнозов за 24 часа</span><strong>{{ $forecastCount24h }}</strong></div>
                    <div class="admin-status-row"><span>Реакций за последний час</span><strong>{{ $reactionCount1h }}</strong></div>
                    <div class="admin-status-row"><span>Пляжей в избранном</span><strong>{{ $favoriteCount }}</strong></div>
                    <div class="admin-status-row"><span>Посетителей с избранным</span><strong>{{ $favoriteVisitorCount }}</strong></div>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
