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
            @unless($isOperator ?? false)
                <button id="secret-login-btn" class="topbar-auth-btn" type="button" aria-label="Авторизация">
                    <img src="{{ asset('значки и иконки/user-cog.svg') }}" alt="">
                </button>
            @endunless
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
                                <button type="button" class="mobile-legend-button danger" data-category="danger" data-legend-level="danger">Опасно</button>
                                <button type="button" class="mobile-legend-button caution" data-category="caution" data-legend-level="caution">Умеренно опасно</button>
                                <button type="button" class="mobile-legend-button safe" data-category="safe" data-legend-level="safe">Безопасно</button>
                            </div>
                        </div>
                        <div class="mobile-legend-detail" aria-live="polite"></div>

                        <div class="legend-descriptions" aria-label="Описание уровней волнения">
                            <button type="button" class="legend-description-card safe" data-category="safe" aria-label="Показать безопасные пляжи">
                                <img src="{{ asset('./separate-flag-colors(green).png') }}" alt="Безопасно">
                                <div class="legend-description-text">
                                    <h3>Зелёный уровень — безопасно</h3>
                                    <p><strong>Спокойное море.</strong> Купание разрешено.</p>
                                </div>
                            </button>
                            <button type="button" class="legend-description-card caution" data-category="caution" aria-label="Показать умеренно опасные пляжи">
                                <img src="{{ asset('./separate-flag-colors(yellow).png') }}" alt="Внимание">
                                <div class="legend-description-text">
                                    <h3>Жёлтый уровень — умеренно опасно</h3>
                                    <p><strong>Повышенное волнение.</strong> Будьте внимательны и осторожны.</p>
                                </div>
                            </button>
                            <button type="button" class="legend-description-card danger" data-category="danger" aria-label="Показать опасные пляжи">
                                <img src="{{ asset('./separate-flag-colors(red).png') }}" alt="Опасно">
                                <div class="legend-description-text">
                                    <h3>Красный уровень — опасно</h3>
                                    <p><strong>Сильное волнение.</strong> Опасно.</p>
                                </div>
                            </button>
                        </div>
                        <button type="button" class="legend-panel-hint">Нажмите чтобы выбрать категорию</button>
                        <div class="legend-extra-details" aria-label="Подробности уровней волнения">
                            <article class="legend-extra-detail safe">
                                <h4>Описание</h4>
                                <p>Высота волн до 1,2 м. Условия в целом безопасны, но необходимо соблюдать обычные меры предосторожности.</p>
                                <p><strong>Рекомендация:</strong> можно купаться, не заплывая за буйки.</p>
                            </article>
                            <article class="legend-extra-detail caution">
                                <h4>Описание</h4>
                                <p>Высота волн от 1,2 до 1,5 м. Возможны сложности при входе и выходе из воды, а также риск обратного течения.</p>
                                <p><strong>Рекомендация:</strong> детям, пожилым людям и неуверенным пловцам купаться не рекомендуется.</p>
                            </article>
                            <article class="legend-extra-detail danger">
                                <h4>Описание</h4>
                                <p>Высота волн более 1,5 м. Купание может быть опасным из-за сильных волн и риска быть унесённым в море.</p>
                                <p><strong>Рекомендация:</strong> не входить в воду и следовать указаниям спасателей.</p>
                            </article>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section id="list-screen" class="screen">
            <div class="filter-panel">
                <div class="filter-copy-block">
                    <h3 class="filter-title">Поиск и фильтры</h3>
                    <p class="filter-description">Список обновляется мгновенно по названию и категории безопасности.</p>
                </div>
                <div class="search-row">
                    <input id="search-input" class="search-input" type="text" placeholder="Введите часть названия пляжа">
                    <div class="search-row-actions">
                        <div class="search-action-block">
                            <button type="button" id="clear-search-button" class="clear-search-button">Очистить поиск</button>
                            <h2 class="screen-title search-results-title">Найдено пляжей: <span id="results-counter" class="counter-badge">0</span></h2>
                        </div>
                    </div>
                </div>
                <div class="filter-chips">
                    <div class="filter-chips-title">Выбрать категорию волнения</div>
                    <button type="button" class="filter-chip active" data-category="all">Все пляжи</button>
                    <button type="button" class="filter-chip" data-category="caution">Умеренно опасно</button>
                    <button type="button" class="filter-chip" data-category="safe">Безопасно</button>
                    <button type="button" class="filter-chip" data-category="danger">Опасно</button>
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

<!-- Модальное окно авторизации (изначально скрыто) -->
<div id="login-modal" class="modal-overlay hidden">
    <div class="modal-content auth-modal-content">
        <button id="close-modal-btn" class="close-btn" type="button">&times;</button>
        <div class="auth-card">
            <img class="auth-logo" src="{{ asset('супер-пупер логотип сайта.png') }}" alt="Логотип сайта">
            <h2 id="auth-title">Авторизация</h2>
            <p id="auth-subtitle" class="modal-subtitle">Войдите или создайте аккаунт пользователя сайта.</p>

            <div class="auth-tabs" role="tablist" aria-label="Режим авторизации">
                <button type="button" class="auth-tab active" data-auth-mode="login">Вход</button>
                <button type="button" class="auth-tab" data-auth-mode="register">Регистрация</button>
            </div>

            <div id="auth-message" class="auth-message hidden" aria-live="polite"></div>

            <form id="visitor-login-form" class="auth-form" data-auth-panel="login">
                <div class="auth-field">
                    <label for="visitor-login-identifier">Ник или email</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="text" id="visitor-login-identifier" name="identifier" placeholder="nickname или you@example.com" required autocomplete="username" data-auth-validate="identifier">
                    </div>
                </div>
                <div class="auth-field">
                    <label for="visitor-login-password">Пароль</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="password" id="visitor-login-password" name="password" placeholder="Минимум 8 символов" required autocomplete="current-password" data-auth-validate="password">
                        <button type="button" class="auth-password-toggle" data-password-target="visitor-login-password">Показать</button>
                    </div>
                </div>
                <button type="submit" class="primary-btn auth-submit-button">Войти</button>
            </form>

            <form id="visitor-register-form" class="auth-form hidden" data-auth-panel="register">
                <div class="auth-field">
                    <label for="visitor-register-nickname">Ник</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="text" id="visitor-register-nickname" name="nickname" placeholder="3-32 символа, буквы, цифры, _ или -" required autocomplete="nickname" data-auth-validate="nickname">
                    </div>
                </div>
                <div class="auth-field">
                    <label for="visitor-register-email">Email</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="email" id="visitor-register-email" name="email" placeholder="you@example.com" required autocomplete="email" data-auth-validate="email">
                    </div>
                </div>
                <div class="auth-fio-grid" aria-label="Необязательные ФИО">
                    <div class="auth-field">
                        <label for="visitor-register-last-name">Фамилия</label>
                        <div class="auth-input-shell auth-input-shell-optional">
                            <input class="auth-input" type="text" id="visitor-register-last-name" name="last_name" placeholder="Необязательно" autocomplete="family-name" data-auth-validate="optional-text">
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="visitor-register-first-name">Имя</label>
                        <div class="auth-input-shell auth-input-shell-optional">
                            <input class="auth-input" type="text" id="visitor-register-first-name" name="first_name" placeholder="Необязательно" autocomplete="given-name" data-auth-validate="optional-text">
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="visitor-register-middle-name">Отчество</label>
                        <div class="auth-input-shell auth-input-shell-optional">
                            <input class="auth-input" type="text" id="visitor-register-middle-name" name="middle_name" placeholder="Необязательно" autocomplete="additional-name" data-auth-validate="optional-text">
                        </div>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="visitor-register-password">Пароль</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="password" id="visitor-register-password" name="password" placeholder="Минимум 8 символов" required autocomplete="new-password" data-auth-validate="password">
                        <button type="button" class="auth-password-toggle" data-password-target="visitor-register-password">Показать</button>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="visitor-register-password-confirmation">Повтор пароля</label>
                    <div class="auth-input-shell is-invalid">
                        <input class="auth-input" type="password" id="visitor-register-password-confirmation" name="password_confirmation" placeholder="Повторите пароль" required autocomplete="new-password" data-auth-validate="password-confirmation" data-password-source="visitor-register-password">
                        <button type="button" class="auth-password-toggle" data-password-target="visitor-register-password-confirmation">Показать</button>
                    </div>
                </div>
                <div class="auth-field">
                    <label for="visitor-register-code">Код подтверждения</label>
                    <div class="auth-input-shell auth-code-shell is-invalid">
                        <input class="auth-input" type="text" id="visitor-register-code" name="verification_code" placeholder="6 цифр" required inputmode="numeric" maxlength="6" autocomplete="one-time-code" data-auth-validate="code">
                        <button type="button" id="visitor-send-code-button" class="auth-send-code-button">Отправить код</button>
                    </div>
                </div>
                <button type="submit" class="primary-btn auth-submit-button">Зарегистрироваться</button>
            </form>

            <div class="auth-footer">
                <button type="button" id="auth-clear-button" class="auth-link-button">Очистить</button>
            </div>
        </div>
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
