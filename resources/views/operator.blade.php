<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель оператора — {{ $beach->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* === УНИВЕРСАЛЬНЫЕ ПРАВИЛА ДЛЯ ШРИФТА И ЦВЕТА === */
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            /* Принудительно задаем единый цвет текста везде */
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .operator-container {
            background: #ffffff;
            width: 100%;
            max-width: 550px;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
            margin: 20px;
        }

        .operator-header h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 700;
        }

        .beach-name {
            font-size: 18px;
            margin: 0 0 24px 0;
            font-weight: 600;
        }

        .instruction {
            font-size: 15px;
            margin-bottom: 16px;
        }

        /* Удобная сетка для 7 кнопок статуса */
        .beaufort-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .beaufort-grid button {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 14px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            outline: none;
        }

        .beaufort-grid button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* Подсветка выбранной кнопки (стандартной) */
        .beaufort-grid button.selected {
            background: #e2e8f0;
            border-color: #94a3b8;
            font-weight: 600;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
        }

        /* Кнопка опасности (Оставляем красной для безопасности) */
        .beaufort-grid button.hazard-btn {
            grid-column: span 2;
            justify-content: center;
            background: #fff5f5;
            border-color: #fee2e2;
            color: #991b1b;
            /* Красный текст для привлечения внимания */
        }

        .beaufort-grid button.hazard-btn:hover {
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .beaufort-grid button.hazard-btn.selected {
            background: #fef2f2;
            border-color: #ef4444;
            color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .status-icon {
            font-size: 18px;
        }

        .operator-actions {
            display: flex;
            gap: 12px;
        }

        .btn-save {
            flex: 2;
            background: #3b82f6;
            color: white;
            /* Белый текст для читаемости на синем фоне */
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-save:hover:not(:disabled) {
            background: #2563eb;
        }

        .btn-save:disabled {
            background: #94a3b8;
            color: #f1f5f9;
            /* Светлый текст на заблокированной кнопке */
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-cancel {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #e2e8f0;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s ease;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }
    </style>
</head>

<body>

    <div id="operator-page" class="operator-container" data-beach-id="{{ $beach->id }}">
        <div class="operator-header">
            <h2>Панель оператора</h2>
            <p class="beach-name" id="operator-panel-beach-name">{{ $beach->name }}</p>
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

            <div class="operator-actions">
                <a href="/?beach={{ $beach->id }}" class="btn-cancel">Отмена</a>
                <button id="submit-operator-data" class="btn-save" disabled>Сохранить</button>
            </div>
        </div>
    </div>

</body>

</html>