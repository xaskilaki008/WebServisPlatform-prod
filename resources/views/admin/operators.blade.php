<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление операторами</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <p class="admin-kicker">operators</p>
                    <h1>Управление операторами</h1>
                </div>
                <div class="admin-header-actions">
                    <a class="admin-nav-button admin-home-button" href="/" aria-label="Вернуться на сайт"></a>
                    <form method="POST" action="/admin/logout" onsubmit="return confirm('Выйти из панели администратора?')">
                        @csrf
                        <button type="submit" class="action-button secondary">Выйти</button>
                    </form>
                </div>
            </header>

            @include('admin.partials.nav', ['active' => 'operators'])

            @if(session('status'))
                <div class="admin-flash">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="admin-flash error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-flash error">{{ $errors->first() }}</div>
            @endif

            <section class="admin-section">
                <h2>Существующие операторы</h2>
                <p class="admin-note">Оператором считается пользователь с ролью <code>operator</code> и профилем в таблице <code>operators</code>. Закрепления хранятся в <code>operator_beach</code>.</p>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Телефон</th>
                                <th>Пляжи</th>
                                <th>Обновить</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <strong>{{ $user->login ?? $user->name }}</strong>
                                        <div class="admin-muted">{{ $user->role?->name ?? '-' }}</div>
                                    </td>
                                    <td colspan="3">
                                        <form method="POST" action="/admin/operators/{{ $user->id }}" class="admin-operator-form">
                                            @csrf
                                            <input type="text" name="work_phone" value="{{ old('work_phone', $user->operator?->work_phone) }}" placeholder="Рабочий телефон">
                                            <div class="admin-checkbox-field">
                                                <span class="admin-muted">Выберите один или несколько пляжей</span>
                                                <div class="admin-checkbox-list">
                                                    @foreach($beaches as $beach)
                                                        <label class="admin-checkbox-item">
                                                            <input
                                                                type="checkbox"
                                                                name="beach_ids[]"
                                                                value="{{ $beach->id }}"
                                                                @checked($user->operator?->beaches->contains('id', $beach->id))
                                                            >
                                                            <span>{{ $beach->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <button type="submit" class="action-button primary">Сохранить оператора</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $users->links() }}
            </section>

            <section class="admin-section">
                <h2>Назначить нового оператора</h2>
                <div class="admin-grid">
                    @foreach($candidateUsers as $candidate)
                        <form method="POST" action="/admin/operators/{{ $candidate->id }}" class="admin-mini-card">
                            @csrf
                            <strong>{{ $candidate->login ?? $candidate->email }}</strong>
                            <span class="admin-muted">Текущая роль: {{ $candidate->role?->name ?? '-' }}</span>
                            <input type="text" name="work_phone" placeholder="Рабочий телефон">
                            <div class="admin-checkbox-field">
                                <span class="admin-muted">Выберите один или несколько пляжей</span>
                                <div class="admin-checkbox-list">
                                    @foreach($beaches as $beach)
                                        <label class="admin-checkbox-item">
                                            <input type="checkbox" name="beach_ids[]" value="{{ $beach->id }}">
                                            <span>{{ $beach->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <button type="submit" class="action-button secondary">Назначить оператором</button>
                        </form>
                    @endforeach
                </div>
            </section>
        </section>
    </main>
</body>
</html>
