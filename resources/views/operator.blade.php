<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель оператора — {{ $beach->name }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            box-sizing: border-box;
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Сильно уменьшенная и компактная панель */
        .operator-container {
            background: #ffffff;
            width: 100%;
            max-width: 360px;
            /* Сузили контейнер */
            padding: 16px;
            /* Уменьшили отступы */
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .operator-header h2 {
            margin: 0 0 2px 0;
            font-size: 16px;
            font-weight: 700;
        }

        .beach-name {
            font-size: 13px;
            color: #2563eb;
            margin: 0 0 12px 0;
            font-weight: 600;
        }

        .instruction {
            font-size: 12px;
            margin-bottom: 8px;
            color: #475569;
        }

        /* Сетка для PNG значков */
        .beaufort-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            margin-bottom: 14px;
        }

        .beaufort-grid button {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        /* Значки PNG из папки согласно ТЗ (максимум до 200х200px) */
        .operator-icon-img {
            width: 100%;
            max-width: 40px;
            /* Компактный размер для аккуратного вида */
            height: auto;
            max-height: 40px;
            object-fit: contain;
        }

        .beaufort-grid button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .beaufort-grid button.selected {
            background: #fef08a;
            /* Желтый фокус */
            border-color: #eab308;
            font-weight: 600;
        }

        /* Кнопка опасности — ТЕКСТ СТРОГО ЧЕРНЫЙ */
        .beaufort-grid button.hazard-btn {
            grid-column: span 2;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .beaufort-grid button.hazard-btn span.hazard-text-label {
            color: #000000 !important;
            /* Текст строго черный */
            font-weight: 700;
        }

        .beaufort-grid button.hazard-btn.selected {
            background: #fca5a5;
            border-color: #ef4444;
        }

        .operator-actions {
            display: flex;
            gap: 6px;
        }

        /* Кнопка сохранения — ТЕКСТ СТРОГО ЧЕРНЫЙ */
        .btn-save {
            flex: 2;
            background: #facc15;
            color: #000000 !important;
            /* Текст строго черный */
            border: 1px solid #eab308;
            padding: 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-save:disabled {
            background: #cbd5e1;
            border-color: #94a3b8;
            color: #64748b !important;
            cursor: not-allowed;
        }

        .btn-cancel {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #e2e8f0;
            text-decoration: none;
            padding: 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div id="operator-page" class="operator-container" data-beach-id="{{ $beach->id }}">
        <div class="operator-header">
            <h2>Панель оператора</h2>
            <p class="beach-name">{{ $beach->name }}</p>
        </div>

        <div class="operator-body">
            <p class="instruction">Фактическое состояние (Бофорт):</p>

            <div class="beaufort-grid">
                <button class="status-btn" data-value="0">
                    <img src="/значки и иконки/operator-simbols/0.png" class="operator-icon-img" alt="0">
                    <span>0 баллов</span>
                </button>
                <button class="status-btn" data-value="1">
                    <img src="/значки и иконки/operator-simbols/1.png" class="operator-icon-img" alt="1">
                    <span>1 балл</span>
                </button>
                <button class="status-btn" data-value="2">
                    <img src="/значки и иконки/operator-simbols/2.png" class="operator-icon-img" alt="2">
                    <span>2 балла</span>
                </button>
                <button class="status-btn" data-value="3">
                    <img src="/значки и иконки/operator-simbols/3.png" class="operator-icon-img" alt="3">
                    <span>3 балла</span>
                </button>
                <button class="status-btn" data-value="4">
                    <img src="/значки и иконки/operator-simbols/4.png" class="operator-icon-img" alt="4">
                    <span>4 балла</span>
                </button>
                <button class="status-btn" data-value="5">
                    <img src="/значки и иконки/operator-simbols/5.png" class="operator-icon-img" alt="5">
                    <span>5 баллов</span>
                </button>

                <button class="status-btn hazard-btn" data-value="hazard">
                    <img src="/значки и иконки/operator-simbols/hazard.png" class="operator-icon-img" alt="⚠️">
                    <span class="hazard-text-label">Опасность</span>
                </button>
            </div>

            <div class="operator-actions">
                <a href="/?beach={{ $beach->id }}" class="btn-cancel">Отмена</a>
                <button id="submit-operator-data" class="btn-save" disabled>Сохранить</button>
            </div>
        </div>
    </div>

</body>

</html>