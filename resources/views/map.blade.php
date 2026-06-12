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
                <a href="/" class="topbar-logo-link" aria-label="На главную страницу">
                    <img class="topbar-logo" src="{{ asset('супер-пупер логотип сайта.png') }}" alt="Логотип сайта">
                </a>
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
                        <div class="mobile-legend-compact">
                            <img class="mobile-legend-image" src="{{ asset('./flag-colors.png') }}" alt="Цвета флажков">
                            <div class="mobile-legend-actions" aria-label="Уровни волнения">
                                <button type="button" class="mobile-legend-button danger" data-category="danger" data-legend-level="danger">Опасно — купание запрещено</button>
                                <button type="button" class="mobile-legend-button caution" data-category="caution" data-legend-level="caution">Умеренно опасно — будьте осторожны</button>
                                <button type="button" class="mobile-legend-button safe" data-category="safe" data-legend-level="safe">Безопасно — купание разрешено</button>
                            </div>
                        </div>
                        <div class="mobile-legend-detail" aria-live="polite"></div>

                        <div class="legend-descriptions" aria-label="Описание уровней волнения">
                            <button type="button" class="legend-description-card safe" data-category="safe" aria-label="Показать пляжи, где купание допустимо">
                                <img src="{{ asset('./separate-flag-colors(green).png') }}" alt="Безопасно">
                                <div class="legend-description-text">
                                    <h3>Зелёный уровень — безопасно</h3>
                                    <p><strong>Спокойное море.</strong> Купание разрешено.</p>
                                    <p>Высота волн до 1,2 м. Условия в целом безопасны, но необходимо соблюдать обычные меры предосторожности.</p>
                                    <p><strong>Рекомендация:</strong> можно купаться, не заплывая за буйки.</p>
                                </div>
                            </button>
                            <button type="button" class="legend-description-card caution" data-category="caution" aria-label="Показать пляжи, где нужна осторожность">
                                <img src="{{ asset('./separate-flag-colors(yellow).png') }}" alt="Внимание">
                                <div class="legend-description-text">
                                    <h3>Жёлтый уровень — умеренно опасно</h3>
                                    <p><strong>Повышенное волнение.</strong> Будьте внимательны и осторожны.</p>
                                    <p>Высота волн от 1,2 до 1,5 м. Возможны сложности при входе и выходе из воды, а также риск обратного течения.</p>
                                    <p><strong>Рекомендация:</strong> детям, пожилым людям и неуверенным пловцам купаться не рекомендуется.</p>
                                </div>
                            </button>
                            <button type="button" class="legend-description-card danger" data-category="danger" aria-label="Показать пляжи, где купание не рекомендуется">
                                <img src="{{ asset('./separate-flag-colors(red).png') }}" alt="Опасно">
                                <div class="legend-description-text">
                                    <h3>Красный уровень — опасно</h3>
                                    <p><strong>Сильное волнение.</strong> Купание запрещено.</p>
                                    <p>Высота волн более 1,5 м. Купание может быть опасным из-за сильных волн и риска быть унесённым в море.</p>
                                    <p><strong>Рекомендация:</strong> не входить в воду и следовать указаниям спасателей.</p>
                                </div>
                            </button>
                        </div>
                        <p class="legend-panel-hint">Нажмите чтобы выбрать категорию</p>
                    </div>
                </aside>
            </div>
        </section>

        <section id="list-screen" class="screen">
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
                    <div class="search-row-actions">
                        <button type="button" id="clear-search-button" class="clear-search-button">Очистить поиск</button>
                        <h2 class="screen-title search-results-title">Найдено пляжей: <span id="results-counter" class="counter-badge">0</span></h2>
                    </div>
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
