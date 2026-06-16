<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пляжами</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <p class="admin-kicker">beaches</p>
                    <h1>Управление пляжами</h1>
                </div>
                <div class="admin-header-actions">
                    <a class="admin-nav-button admin-home-button" href="/" aria-label="Вернуться на сайт"></a>
                    <form method="POST" action="/admin/logout" onsubmit="return confirm('Выйти из панели администратора?')">
                        @csrf
                        <button type="submit" class="action-button secondary">Выйти</button>
                    </form>
                </div>
            </header>

            @include('admin.partials.nav', ['active' => 'beaches'])

            <section class="admin-section">
                <h2>Список пляжей</h2>
                <p class="admin-note">Эта страница пока показывает существующие пляжи и закреплённых операторов без изменения геометрии и координат.</p>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Координаты</th>
                                <th>Уровень</th>
                                <th>Операторы</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($beaches as $beach)
                                @php
                                    $beachOperators = $assignments
                                        ->filter(fn ($operator) => $operator->beaches->contains('id', $beach->id))
                                        ->map(fn ($operator) => $operator->user?->login ?? $operator->user?->email ?? 'operator #' . $operator->id)
                                        ->values();
                                @endphp
                                <tr>
                                    <td>{{ $beach->id }}</td>
                                    <td><strong>{{ $beach->name }}</strong></td>
                                    <td>{{ $beach->latitude }}, {{ $beach->longitude }}</td>
                                    <td>{{ $beach->wave_level }}</td>
                                    <td>{{ $beachOperators->isNotEmpty() ? $beachOperators->join(', ') : 'не закреплены' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $beaches->links() }}
            </section>
        </section>
    </main>
</body>
</html>
