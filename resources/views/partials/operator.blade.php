<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель оператора - {{ $beach->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Немного стилей, чтобы панель была по центру отдельной страницы */
        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: sans-serif;
        }

        .operator-page-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 600px;
        }

        .cancel-btn {
            text-decoration: none;
            padding: 10px 20px;
            background: #e2e8f0;
            color: #333;
            border-radius: 6px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        .cancel-btn:hover {
            background: #cbd5e1;
        }
    </style>
</head>

<body>
    <div id="operator-page" class="operator-page-container" data-beach-id="{{ $beach->id }}">

        <div class="operator-header">
            <h2>Панель оператора</h2>
            <p id="operator-panel-beach-name" style="font-size: 18px; font-weight: bold; color: #3b82f6;">
                {{ $beach->name }}</p>
        </div>

        <div class="operator-body">
            <p class="instruction">Укажите фактическое состояние акватории:</p>

            <div class="beaufort-grid">
                <button class="status-btn" data-value="0"><span class="status-icon">🪞</span> 0 баллов</button>
                <button class="status-btn" data-value="1"><span class="status-icon">💧</span> 1 балл</button>
                <button class="status-btn" data-value="2"><span class="status-icon">🌊</span> 2 балла</button>
                <button class="status-btn" data-value="3"><span class="status-icon">🌊</span> 3 балла</button>
                <button class="status-btn" data-value="4"><span class="status-icon">🌊</span> 4 балла</button>
                <button class="status-btn" data-value="5"><span class="status-icon">🌊</span> 5 баллов</button>
                <button class="status-btn hazard-btn" data-value="hazard"><span class="status-icon">🛢️</span>
                    Опасность</button>
            </div>

            <div class="operator-actions" style="display: flex; gap: 15px; margin-top: 25px;">
                <a href="/?beach={{ $beach->id }}" class="cancel-btn">Отмена</a>
                <button id="submit-operator-data" class="save-btn" disabled style="flex: 1;">Сохранить
                    изменения</button>
            </div>
        </div>
    </div>
</body>

</html>