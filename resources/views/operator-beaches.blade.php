<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Выбор пляжа оператора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="operator-page">
    <main class="operator-shell">
        <section class="operator-card">
            <header class="operator-header">
                <div>
                    <p class="operator-kicker">Панель оператора</p>
                    <h1>Выберите пляж</h1>
                </div>
                <div class="operator-header-actions">
                    <a class="operator-back-link" href="/operator/history">История действий</a>
                    <a class="operator-back-link" href="/">Карта</a>
                </div>
            </header>

            <div class="operator-readonly">
                @forelse($beaches as $beach)
                    <a class="operator-readonly-item" href="/operator/{{ $beach->id }}">
                        <span class="operator-readonly-title">Пляж ID {{ $beach->id }}</span>
                        <div>{{ $beach->name }}</div>
                    </a>
                @empty
                    <div class="operator-error">За оператором пока не закреплены пляжи.</div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
