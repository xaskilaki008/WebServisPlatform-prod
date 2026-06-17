<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История действий оператора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="operator-page">
    @php
        $statusLabels = [
            '0' => 'Зеркально-гладкая',
            '1' => 'Рябь',
            '2' => 'Небольшие гребни волн',
            '3' => 'Гребни начинают опрокидываться',
            '4' => 'Местами появляются барашки',
            '5' => 'Повсюду образуются барашки',
            '6' => 'Крупные гребни, ветер срывает пену',
            'hazard' => 'Особое предупреждение',
        ];

        $directionLabels = [
            'direct' => 'Прямо на пляж',
            'left' => 'Слева на пляж',
            'right' => 'Справа на пляж',
            'azimuth' => 'По азимуту',
            'chaotic' => 'Не определить',
        ];

        $accessLabels = [
            'open' => 'Пляж открыт',
            'limited' => 'Ограниченный доступ',
            'closed' => 'Пляж закрыт',
        ];
    @endphp

    <main class="operator-shell">
        <section class="operator-card">
            <header class="operator-header">
                <div>
                    <p class="operator-kicker">Панель оператора</p>
                    <h1>История действий</h1>
                </div>
                <div class="operator-header-actions">
                    <a class="operator-back-link" href="/operator">Назад</a>
                    <a class="operator-back-link" href="/">Карта</a>
                </div>
            </header>

            <section class="operator-history-list" aria-label="История отправленных данных оператора">
                @forelse($logs as $log)
                    <article class="operator-history-card">
                        <div class="operator-history-main">
                            <span class="operator-readonly-title">{{ $log->submitted_at?->format('d.m.Y H:i') ?? '-' }}</span>
                            <h2>{{ $log->beach?->name ?? 'Пляж #' . $log->beach_id }}</h2>
                        </div>
                        <dl class="operator-history-grid">
                            <div>
                                <dt>Состояние моря</dt>
                                <dd>{{ $statusLabels[(string) $log->operator_status] ?? $log->operator_status ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt>Направление</dt>
                                <dd>
                                    {{ $directionLabels[$log->operator_wave_direction] ?? $log->operator_wave_direction ?? '-' }}
                                    @if($log->operator_wave_direction === 'azimuth' && $log->operator_wave_azimuth !== null)
                                        {{ $log->operator_wave_azimuth }}°
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Период</dt>
                                <dd>{{ $log->operator_wave_period ? $log->operator_wave_period . ' сек' : '-' }}</dd>
                            </div>
                            <div>
                                <dt>Доступность</dt>
                                <dd>{{ $accessLabels[$log->operator_access_status] ?? $log->operator_access_status ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt>Актуально до</dt>
                                <dd>{{ $log->expires_at?->format('d.m.Y H:i') ?? '-' }}</dd>
                            </div>
                        </dl>
                        @if($log->operator_warning)
                            <p class="operator-history-warning">{{ $log->operator_warning }}</p>
                        @endif
                    </article>
                @empty
                    <div class="operator-error">История действий пока пустая.</div>
                @endforelse
            </section>
        </section>
    </main>
</body>
</html>
