<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход администратора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card admin-login-card">
            <h1>Вход администратора</h1>

            @if($errors->any())
                <div class="admin-flash error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="/admin/login" class="admin-form">
                @csrf
                <label>
                    <span>Логин</span>
                    <input type="text" name="login" value="{{ old('login') }}" required autocomplete="username">
                </label>
                <label>
                    <span>Пароль</span>
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button type="submit" class="action-button primary">Войти</button>
            </form>
        </section>
    </main>
</body>
</html>
