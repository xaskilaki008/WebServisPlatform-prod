<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал действий</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <p class="admin-kicker">audit log</p>
                    <h1>Журнал действий</h1>
                </div>
                <form method="POST" action="/admin/logout">
                    @csrf
                    <button type="submit" class="action-button secondary">Выйти</button>
                </form>
            </header>

            @include('admin.partials.nav', ['active' => 'logs'])

            <section class="admin-section">
                <h2>Существующие операторы</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Пляжи</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($operators as $operator)
                                <tr>
                                    <td>{{ $operator->id }}</td>
                                    <td>{{ $operator->user?->login ?? $operator->user?->email ?? '-' }}</td>
                                    <td>{{ $operator->beaches->pluck('name')->join(', ') ?: 'не закреплены' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-section">
                <h2>Последние действия пользователей</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Время</th>
                                <th>Пользователь</th>
                                <th>Действие</th>
                                <th>Сущность</th>
                                <th>Описание</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>{{ $log->created_at }}</td>
                                    <td>
                                        {{ $log->user?->login ?? $log->user?->email ?? 'guest' }}
                                        <div class="admin-muted">{{ $log->user?->role?->name }}</div>
                                    </td>
                                    <td><code>{{ $log->action }}</code></td>
                                    <td>
                                        @if($log->entity_type)
                                            {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $log->description }}</td>
                                    <td>{{ $log->ip_address }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $logs->links() }}
            </section>
        </section>
    </main>
</body>
</html>
