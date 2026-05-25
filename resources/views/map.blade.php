<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Флаг-пляж – Морское волнение.</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
    <script>
        window.operatorContext = {
            isOperator: @json($isOperator ?? false),
            operatorBeachId: @json($operatorBeachId ?? null),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="topbar-title-wrap">
                <p class="topbar-title">Мониторинг морского волнения у пляжей Севастополя</p>
                <p class="topbar-subtitle">Будьте в курсе доступности пляжей любимого моря</p>
            </div>
            <div class="topbar-logo-wrap">
                <img class="topbar-logo" src="{{ asset('супер-пупер логотип сайта.png') }}" alt="Логотип сайта">
            </div>
            <div class="topbar-nav">
                <button type="button" class="nav-button active" data-screen-target="map-screen">Карта</button>
                <button type="button" class="nav-button" data-screen-target="list-screen">Список пляжей</button>
            </div>
            @if(($isOperator ?? false) && $operatorBeachId)
                <div class="topbar-actions">
                    <a class="topbar-operator-link" href="/operator/{{ $operatorBeachId }}">Панель оператора</a>
                </div>
            @endif
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
                    </div>
                    <div id="map"></div>
                    <button id="toggle-map-size-button" type="button" class="map-control-button map-expand-button" aria-label="Развернуть карту" title="Развернуть карту">
                        <img src="{{ asset('значки и иконки/map-controls/expand-map.png') }}" alt="">
                    </button>
                </section>
                <aside class="left-column">
                    <div class="panel legend-panel">
                        <p class="legend-panel-hint">Нажмите чтобы выбрать категорию из списка</p>
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
            @if($isOperator ?? false)
                <div class="operator-warning-panel">
                    <div>
                        <strong>Режим оператора</strong>
                        <span>Доступно управление только пляжем ID {{ $operatorBeachId }}.</span>
                    </div>
                    <button type="button" id="operator-refresh-lists" class="action-button primary small">Обновить списки</button>
                </div>
            @endif
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
            <div class="favorites-panel">
                <h3 class="filter-title">Избранные пляжи</h3>
                <div id="favorites-list" class="favorites-list">
                    <div class="empty-state compact">Избранные пляжи пока не добавлены.</div>
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
            @include('partials.detail-card')
        </section>
    </main>
</div>
<!-- Скрытая кнопка для вызова окна авторизации -->
@unless($isOperator ?? false)
<button id="secret-login-btn" class="ghost-btn" aria-label="Вход для сотрудников">
    <img src="{{ asset('значки и иконки/user-cog.svg') }}" alt="">
</button>
@endunless

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
        @if($isOperator ?? false)
            <button type="button" id="operator-logout-button" class="logout-button">Log-out</button>
        @endif
    </div>
</div>
<div id="logout-confirm-modal" class="modal-overlay hidden">
    <div class="modal-content">
        <button id="close-logout-confirm-btn" class="close-btn" type="button">&times;</button>
        <h2>Подтвердите выход</h2>
        <p class="modal-subtitle">Вы уверены, что хотите выйти из режима оператора?</p>
        <div class="modal-actions">
            <button type="button" id="confirm-logout-button" class="primary-btn danger">Выйти</button>
            <button type="button" id="cancel-logout-button" class="secondary-btn">Отмена</button>
        </div>
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
