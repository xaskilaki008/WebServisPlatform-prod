<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи и роли</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <h1>Пользователи и роли</h1>
                </div>
                <div class="admin-header-actions">
                    <a class="admin-nav-button admin-home-button" href="/" aria-label="Вернуться на сайт"></a>
                    <form method="POST" action="/admin/logout" onsubmit="return confirm('Выйти из панели администратора?')">
                        @csrf
                        <button type="submit" class="action-button secondary">Выйти</button>
                    </form>
                </div>
            </header>

            @include('admin.partials.nav', ['active' => 'users'])

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
                <h2>Учётные записи</h2>
                <p class="admin-note">Роли назначаются по системному имени роли, а не по id. Собственную роль менять нельзя; последнего активного администратора нельзя отключить.</p>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Активность</th>
                                <th>Оператор</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <strong>{{ $user->login ?? $user->name }}</strong>
                                        <div class="admin-muted">{{ $user->full_name ?: trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '') . ' ' . ($user->middle_name ?? '')) }}</div>
                                    </td>
                                    <td>
                                        {{ $user->email }}
                                        <div class="admin-muted">{{ $user->email_verified_at ? 'email подтверждён' : 'email не подтверждён' }}</div>
                                    </td>
                                    <td>
                                        <form method="POST" action="/admin/users/{{ $user->id }}/role" class="admin-inline-form">
                                            @csrf
                                            <select name="role" @disabled($admin->id === $user->id)>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->name }}" @selected($user->role?->name === $role->name)>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="action-button secondary" @disabled($admin->id === $user->id)>Сохранить</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="/admin/users/{{ $user->id }}/active" class="admin-inline-form">
                                            @csrf
                                            <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                            <button type="submit" class="action-button secondary" @disabled($admin->id === $user->id || ($user->hasRole(\App\Models\Role::ADMIN) && $user->is_active && $activeAdminCount <= 1))>
                                                {{ $user->is_active ? 'Отключить' : 'Включить' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        @if($user->operator)
                                            {{ $user->operator->beaches->pluck('name')->join(', ') ?: 'пляжи не закреплены' }}
                                        @else
                                            <span class="admin-muted">нет профиля</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="admin-action-stack">
                                            <a class="admin-nav-button" href="/admin/operators">Операторы</a>
                                            <form method="POST" action="/admin/users/{{ $user->id }}/ban">
                                                @csrf
                                                <button type="submit" class="action-button danger" @disabled($admin->id === $user->id || ($user->hasRole(\App\Models\Role::ADMIN) && $user->is_active && $activeAdminCount <= 1))>
                                                    Забанить
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $users->links() }}
            </section>
        </section>
    </main>
</body>
</html>
