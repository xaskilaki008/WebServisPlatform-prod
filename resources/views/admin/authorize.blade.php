<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Требуется вход администратора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-auth-gate">
        <a class="admin-nav-button admin-home-button admin-auth-home-button" href="/" aria-label="Вернуться на сайт"></a>
        <a href="/admin/login" class="admin-auth-gate-button">Войти в панель администратора</a>
    </main>
</body>
</html>
