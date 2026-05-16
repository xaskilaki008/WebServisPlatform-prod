<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мониторинг пляжей Севастополя</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="temp-admin-panel">
    <button id="toggle-parsing-btn" class="admin-danger-btn">Парсинг (Вкл/Выкл)</button>
    <button id="force-fetch-btn" class="admin-danger-btn">Взять данные сейчас</button>
</div>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="topbar-title-wrap">
                <h1 class="topbar-title">Мониторинг пляжей Севастополя</h1>
                <p class="topbar-subtitle">Будьте в курсе доступности пляжей любимого моря</p>
            </div>
            <div class="topbar-nav">
                <button type="button" class="nav-button active" data-screen-target="map-screen">Карта</button>
                <button type="button" class="nav-button" data-screen-target="list-screen">Список пляжей</button>
            </div>
        </div>
    </header>

    <main class="page-body">
        <section id="map-screen" class="screen active">
            <div class="map-layout">
                <section class="map-card">
                    <div class="map-toolbar">
                        <div class="map-toolbar-group">
                            <button id="fit-map-button" type="button" class="map-control-button">Показать все</button>
                        </div>
                        <div class="map-toolbar-group">
                            <button id="toggle-map-size-button" type="button" class="map-control-button">Развернуть карту</button>
                        </div>
                    </div>
                    <div id="map"></div>
                </section>
                <aside class="left-column">
                    <div class="panel legend-panel">
                        <div class="legend-text">
                            <h3>Легенда статусов</h3>
                            <p class="legend-meta">Флажок и полигон связаны единым цветом категории безопасности.</p>
                        </div>
                        
                        <img class="mobile-legend-image" src="{{ asset('./flag-colors.png') }}" alt="Цвета флажков">

                        <div class="desktop-legend-images">
                            <img src="{{ asset('./separate-flag-colors(green).png') }}" alt="Безопасно">
                            <img src="{{ asset('./separate-flag-colors(yellow).png') }}" alt="Внимание">
                            <img src="{{ asset('./separate-flag-colors(red).png') }}" alt="Опасно">
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section id="list-screen" class="screen">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title">Найдено пляжей: <span id="results-counter" class="counter-badge">0</span></h2>
                </div>
            </div>
            <div class="filter-panel">
                <h3 class="filter-title">Поиск и фильтры</h3>
                <p class="filter-description">Список обновляется мгновенно по названию и категории безопасности.</p>
                <div class="search-row">
                    <input id="search-input" class="search-input" type="text" placeholder="Введите часть названия пляжа">
                    <button type="button" id="clear-search-button" class="clear-search-button">Очистить поиск</button>
                </div>
                <div class="filter-chips">
                    <button type="button" class="filter-chip active" data-category="all">Все пляжи</button>
                    <button type="button" class="filter-chip" data-category="safe">Купание допустимо</button>
                    <button type="button" class="filter-chip" data-category="caution">Нужна осторожность</button>
                    <button type="button" class="filter-chip" data-category="danger">Купание не рекомендуется</button>
                </div>
            </div>
            <div id="beaches-list" class="list-wrap"></div>
        </section>

        <section id="detail-screen" class="screen">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title">Подробная информация</h2>
                    <p class="screen-subtitle">Детальная карточка выбранного пляжа и его текущего статуса.</p>
                </div>
                <button type="button" id="detail-back-button" class="back-button">back Назад</button>
            </div>
            <article id="detail-card" class="detail-card hidden">
                <div class="detail-info">
                    <h3 id="detail-title">Название пляжа</h3>
            
                    <div class="data-comparison-container">
            
                        <div class="data-column auto-data">
                            <div class="column-header">Система (DWD)</div>
                            <div id="detail-fields" class="fields-list">
                            </div>
                        </div>
            
                        <div class="data-column operator-data">
                            <div class="column-header">Оператор</div>
                            <div class="fields-list">
                                <div class="field-row">
                                    <span class="field-label">Статус:</span>
                                    <span class="field-value" id="operator-status-text">Нет данных</span>
                                </div>
                                <div class="field-row">
                                    <span class="field-label">Обновлено:</span>
                                    <span class="field-value" id="operator-updated-at">-</span>
                                </div>
                            </div>
            
                            <button id="open-operator-btn" class="operator-action-btn">Изменить статус</button>
                        </div>
            
                    </div>
                </div>
            </article>
            
            <div id="operator-panel-modal" class="image-overlay hidden">
                <div class="operator-panel-wrapper">
                    <button id="close-operator-btn" class="close-popup-btn">&times;</button>
            
                    <div class="operator-header">
                        <h2>Панель оператора</h2>
                        <p id="operator-panel-beach-name">Название пляжа</p>
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
                            <button id="submit-operator-data" class="save-btn" disabled>Сохранить изменения</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<!-- Скрытая кнопка для вызова окна авторизации -->
<button id="secret-login-btn" class="ghost-btn" aria-label="Вход для сотрудников">
    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
    </svg>
</button>

<!-- Модальное окно авторизации (изначально скрыто) -->
<div id="login-modal" class="modal-overlay hidden">
    <div class="modal-content">
        <button id="close-modal-btn" class="close-btn">&times;</button>
        <h2>Вход в панель</h2>
        <p class="modal-subtitle">Только для операторов</p>
        
        <!-- Пока форма никуда не отправляет данные, мы добавим это позже -->
        <form id="login-form">
            <div class="input-group">
                <label for="login">Логин</label>
                <!-- Поменяли type на text, id и name на login -->
                <input type="text" id="login" name="login" placeholder="your login (ваш логин)" required autocomplete="username">
            </div>
            <div class="input-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="primary-btn">Войти</button>
        </form>
    </div>
</div>
<button id="scroll-down-btn" class="scroll-down-mobile hidden" aria-label="Прокрутить вниз">↓</button>
<button id="scroll-top-button" class="scroll-top-button" type="button">↑</button>
<!-- Попап для просмотра большой картинки -->
<div id="image-popup" class="image-overlay hidden">
    <div class="popup-wrapper">
        <div id="popup-thumbnails" class="thumbnails-line"></div>

        <div class="popup-image-container">
            <button id="close-image-popup" class="close-popup-btn" onclick="closeImagePopup()">&times;</button>
            <img src="" id="popup-large-photo" class="popup-large-photo" alt="Пляж">
        </div>

        <div class="gallery-controls">
            <button id="popup-prev" class="slider-nav-btn prev" onclick="changePhoto(-1, event)">‹</button>
            <div id="popup-counter" class="photo-number-label"></div>
            <button id="popup-next" class="slider-nav-btn next" onclick="changePhoto(1, event)">›</button>
        </div>
    </div>
</div>

</body>
</html>
