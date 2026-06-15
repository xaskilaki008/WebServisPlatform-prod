<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель оператора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="operator-page">
    @php
        $forecast = $beach->latestForecast;
        $statusOptions = [
            '0' => ['title' => 'Зеркально-гладкая', 'note' => 'Безопасно', 'image' => 'волны без фона/0 балла бофорта.png'],
            '1' => ['title' => 'Рябь', 'note' => 'Безопасно', 'image' => 'волны без фона/1 балла бофорта.png'],
            '2' => ['title' => 'Появляются небольшие гребни волн', 'note' => 'Умеренно опасно', 'image' => 'волны без фона/2 балла бофорта.png'],
            '3' => ['title' => 'Небольшие гребни волн начинают опрокидываться', 'note' => 'Умеренно опасно', 'image' => 'волны без фона/3 балла бофорта.png'],
            '4' => ['title' => 'Хорошо заметны небольшие волны, местами появляются "барашки"', 'note' => 'Опасно', 'image' => 'волны без фона/4 балла бофорта.png'],
            '5' => ['title' => 'Волны принимают хорошо выраженную форму, повсюду образуются "барашки"', 'note' => 'Опасно', 'image' => 'волны без фона/5 балла бофорта.png'],
            '6' => ['title' => 'Появляются гребни большой высоты, ветер срывает пену с гребней', 'note' => 'Опасно', 'image' => 'волны без фона/6 балла бофорта.png'],
            'hazard' => ['title' => 'Особое предупреждение', 'note' => 'Оперативное предупреждение', 'image' => 'hazard.png'],
        ];
    @endphp

    <main class="operator-shell">
        <section class="operator-card">
            <header class="operator-header">
                <div>
                    <p class="operator-kicker">Пляж ID {{ $beach->id }}</p>
                    <h1>{{ $beach->name }}</h1>
                </div>
                <div class="operator-header-actions">
                    <button id="operator-password-button" class="operator-icon-button" type="button" aria-label="Сменить пароль" title="Сменить пароль">
                        <img src="{{ asset('значки и иконки/user-cog.svg') }}" alt="">
                    </button>
                    <a class="operator-back-link" href="/">Карта</a>
                    <a class="operator-back-link" href="/?beach={{ $beach->id }}">Детальная карточка пляжа</a>
                </div>
            </header>

            @if(session('status'))
                <div class="operator-flash">{{ session('status') }}</div>
            @endif

            <div class="temp-admin-panel operator-admin-panel" aria-label="Управление моделью данных">
                <button id="toggle-parsing-btn" class="admin-danger-btn" type="button">Парсинг: вкл/выкл</button>
                <button id="force-fetch-btn" class="admin-danger-btn" type="button">Загрузить модель данных сейчас</button>
            </div>

            <div class="operator-readonly">
                <div class="operator-readonly-item">
                    <span class="operator-readonly-title">Название пляжа</span>
                    <div>{{ $beach->name }}</div>
                </div>
                <div class="operator-readonly-item operator-readonly-forecast">
                    <span class="operator-readonly-title">Внешний прогноз</span>
                    <div class="operator-forecast-summary">
                        <span>Высота волны: {{ $forecast?->wave_height !== null ? $forecast->wave_height . ' м' : 'нет данных' }}</span>
                        <span>Период: {{ $forecast?->wave_period !== null ? $forecast->wave_period . ' сек.' : 'нет данных' }}</span>
                        <span>Направление: {{ $forecast?->wave_direction !== null ? $forecast->wave_direction . '°' : 'нет данных' }}</span>
                        <span>Температура воздуха: {{ $forecast?->air_temp !== null ? $forecast->air_temp . '°C' : 'нет данных' }}</span>
                        <span>Температура воды: {{ $forecast?->water_temp !== null ? $forecast->water_temp . '°C' : 'нет данных' }}</span>
                        <span>Прогноз модели данных на: {{ $forecast?->forecast_time ? $forecast->forecast_time->format('d.m.Y H:i') : 'нет данных' }}</span>
                        <span>Расчёт модели: {{ $forecast?->model_run_at ? $forecast->model_run_at->format('d.m.Y H:i') : 'нет данных' }}</span>
                        <span>Обработано сервером: {{ $forecast?->parsed_at ? $forecast->parsed_at->format('d.m.Y H:i') : 'нет данных' }}</span>
                    </div>
                </div>
            </div>

            <form id="operator-form" method="POST" action="/operator/{{ $beach->id }}" class="operator-form">
                @csrf

                <fieldset class="operator-fieldset">
                    <div class="operator-fieldset-title">Состояние моря</div>
                    <div class="operator-status-picker-row">
                        <button type="button" id="operator-status-picker-button" class="operator-secondary-button">Выбрать состояние моря</button>
                        <div id="operator-status-current" class="operator-status-current">Не выбрано</div>
                    </div>
                </fieldset>

                <label id="operator-warning-field" class="operator-textarea-field hidden">
                    <span>Текст особого предупреждения</span>
                    <textarea name="operator_warning" maxlength="250" rows="3">{{ old('operator_warning', $beach->operator_warning) }}</textarea>
                </label>

                <fieldset class="operator-fieldset">
                    <div class="operator-fieldset-title">Направление волн</div>
                    <div class="operator-radio-list">
                        <label><input type="radio" name="operator_wave_direction" value="direct" @checked(old('operator_wave_direction', $beach->operator_wave_direction) === 'direct')> Прямо на пляж</label>
                        <label><input type="radio" name="operator_wave_direction" value="left" @checked(old('operator_wave_direction', $beach->operator_wave_direction) === 'left')> Слева на пляж</label>
                        <label><input type="radio" name="operator_wave_direction" value="right" @checked(old('operator_wave_direction', $beach->operator_wave_direction) === 'right')> Справа на пляж</label>
                        <label><input type="radio" name="operator_wave_direction" value="azimuth" @checked(old('operator_wave_direction', $beach->operator_wave_direction) === 'azimuth')> Под конкретным направлением</label>
                        <label><input type="radio" name="operator_wave_direction" value="chaotic" @checked(old('operator_wave_direction', $beach->operator_wave_direction, 'chaotic') === 'chaotic')> Не определить (толчея)</label>
                    </div>
                    <label id="operator-azimuth-field" class="operator-number-field hidden">
                        <span>Азимут, градусы</span>
                        <input type="number" name="operator_wave_azimuth" min="0" max="360" value="{{ old('operator_wave_azimuth', $beach->operator_wave_azimuth) }}">
                    </label>
                </fieldset>

                <fieldset class="operator-fieldset">
                    <div class="operator-fieldset-title">Период волн</div>
                    <div class="operator-period-row">
                        <button type="button" id="operator-period-timer" class="operator-secondary-button">Запустить замер</button>
                        <select name="operator_wave_period" id="operator-wave-period">
                            @for($seconds = 2; $seconds <= 12; $seconds++)
                                <option value="{{ $seconds }}" @selected((int) old('operator_wave_period', $beach->operator_wave_period ?? 6) === $seconds)>{{ $seconds }} сек</option>
                            @endfor
                        </select>
                    </div>
                    <div id="operator-timer-note" class="operator-help">Замерьте время между 10 волнами, система разделит результат на 10.</div>
                </fieldset>

                <fieldset class="operator-fieldset">
                    <div class="operator-fieldset-title">Доступность пляжа</div>
                    <select name="operator_access_status">
                        <option value="open" @selected(old('operator_access_status', $beach->operator_access_status ?? 'open') === 'open')>Пляж открыт для всех</option>
                        <option value="limited" @selected(old('operator_access_status', $beach->operator_access_status) === 'limited')>Пляж ограниченно открыт</option>
                        <option value="closed" @selected(old('operator_access_status', $beach->operator_access_status) === 'closed')>Пляж полностью закрыт для купания</option>
                    </select>
                </fieldset>

                <fieldset class="operator-fieldset">
                    <div class="operator-fieldset-title">Срок актуальности</div>
                    <select name="operator_validity">
                        <option value="30m" @selected(old('operator_validity') === '30m')>30 минут</option>
                        <option value="1h" @selected(old('operator_validity', '1h') === '1h')>1 час</option>
                        <option value="3h" @selected(old('operator_validity') === '3h')>3 часа</option>
                        <option value="24h" @selected(old('operator_validity') === '24h')>24 часа</option>
                        <option value="until_disabled" @selected(old('operator_validity') === 'until_disabled')>до отключения</option>
                    </select>
                    <div class="operator-help">Вариант “до отключения” автоматически устареет через 1 месяц.</div>
                </fieldset>

                @if($errors->any())
                    <div class="operator-error">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="operator-save-button">Сохранить и опубликовать</button>
            </form>
        </section>
    </main>

    <div id="operator-status-modal" class="modal-overlay hidden" aria-hidden="true">
        <div class="modal-content operator-status-modal">
            <button id="operator-status-close" class="close-btn" type="button">&times;</button>
            <h2>Состояние моря</h2>
            <p class="modal-subtitle">Выберите наблюдаемое состояние волнения.</p>
            <div class="operator-status-grid">
                @foreach($statusOptions as $value => $option)
                    <label class="operator-status-option">
                        <input
                            form="operator-form"
                            type="radio"
                            name="operator_status"
                            value="{{ $value }}"
                            data-status-title="{{ $option['title'] }}"
                            @checked((string) $beach->operator_status === (string) $value)
                        >
                        <span>
                            <img src="{{ asset('значки и иконки/operator-simbols/' . $option['image']) }}" alt="">
                            <b>{{ $option['title'] }}</b>
                            <small>{{ $option['note'] }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
            <div id="operator-status-confirm" class="operator-status-confirm hidden" aria-live="polite">
                <h3>Подтвердить выбор состояния моря?</h3>
                <p id="operator-status-confirm-text">Выбранное состояние будет применено к форме оператора.</p>
                <div class="operator-status-confirm-actions">
                    <button id="operator-status-confirm-apply" class="operator-save-button" type="button">Подтвердить</button>
                    <button id="operator-status-confirm-cancel" class="operator-secondary-button" type="button">Отмена</button>
                </div>
            </div>
        </div>
    </div>

    <div id="operator-password-modal" class="modal-overlay hidden">
        <div class="modal-content operator-password-modal">
            <button id="operator-password-close" class="close-btn" type="button">&times;</button>
            <button id="operator-modal-logout-button" class="operator-modal-logout-button" type="button">Log-out</button>
            <h2>Смена пароля</h2>
            <p class="modal-subtitle">Введите текущий пароль и новый пароль оператора.</p>

            <form id="operator-password-form" class="operator-password-form">
                <div class="input-group">
                    <label for="current-password">Текущий пароль</label>
                    <input id="current-password" name="current_password" type="password" required autocomplete="current-password">
                </div>
                <div class="input-group">
                    <label for="new-password">Новый пароль</label>
                    <input id="new-password" name="password" type="password" minlength="8" required autocomplete="new-password">
                </div>
                <div class="input-group">
                    <label for="new-password-confirmation">Подтвердите новый пароль</label>
                    <input id="new-password-confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                </div>
                <div id="operator-password-message" class="operator-password-message hidden"></div>
                <button id="operator-password-submit" type="submit" class="operator-save-button">Сменить пароль</button>
            </form>
        </div>
    </div>

    <div id="operator-logout-confirm-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <button id="operator-logout-confirm-close" class="close-btn" type="button">&times;</button>
            <h2>Подтвердите выход</h2>
            <p class="modal-subtitle">Вы уверены, что хотите выйти из режима оператора?</p>
            <div class="modal-actions">
                <button id="operator-logout-confirm-button" class="primary-btn danger" type="button">Выйти</button>
                <button id="operator-logout-cancel-button" class="secondary-btn" type="button">Отмена</button>
            </div>
        </div>
    </div>

    <script>
        const statusInputs = document.querySelectorAll('input[name="operator_status"]');
        const warningField = document.getElementById('operator-warning-field');
        const statusPickerButton = document.getElementById('operator-status-picker-button');
        const statusCurrent = document.getElementById('operator-status-current');
        const statusModal = document.getElementById('operator-status-modal');
        const statusClose = document.getElementById('operator-status-close');
        const statusGrid = statusModal?.querySelector('.operator-status-grid');
        const statusConfirm = document.getElementById('operator-status-confirm');
        const statusConfirmText = document.getElementById('operator-status-confirm-text');
        const statusConfirmApply = document.getElementById('operator-status-confirm-apply');
        const statusConfirmCancel = document.getElementById('operator-status-confirm-cancel');
        const directionInputs = document.querySelectorAll('input[name="operator_wave_direction"]');
        const azimuthField = document.getElementById('operator-azimuth-field');
        const timerButton = document.getElementById('operator-period-timer');
        const timerNote = document.getElementById('operator-timer-note');
        const periodSelect = document.getElementById('operator-wave-period');
        const forceFetchButton = document.getElementById('force-fetch-btn');
        const toggleParsingButton = document.getElementById('toggle-parsing-btn');
        const passwordButton = document.getElementById('operator-password-button');
        const passwordModal = document.getElementById('operator-password-modal');
        const passwordClose = document.getElementById('operator-password-close');
        const passwordForm = document.getElementById('operator-password-form');
        const passwordMessage = document.getElementById('operator-password-message');
        const passwordSubmit = document.getElementById('operator-password-submit');
        const operatorLogoutButton = document.getElementById('operator-modal-logout-button');
        const operatorLogoutConfirmModal = document.getElementById('operator-logout-confirm-modal');
        const operatorLogoutConfirmClose = document.getElementById('operator-logout-confirm-close');
        const operatorLogoutConfirmButton = document.getElementById('operator-logout-confirm-button');
        const operatorLogoutCancelButton = document.getElementById('operator-logout-cancel-button');
        let timerStart = null;
        let confirmedStatusInput = document.querySelector('input[name="operator_status"]:checked') || null;
        let pendingStatusInput = null;
        let applyingConfirmedStatus = false;

        function syncWarningField() {
            const selected = document.querySelector('input[name="operator_status"]:checked')?.value;
            const shouldShow = selected === 'hazard' || Number(selected) >= 4;
            warningField.classList.toggle('hidden', !shouldShow);
        }

        function syncStatusCurrent() {
            const selected = document.querySelector('input[name="operator_status"]:checked');
            statusCurrent.textContent = selected?.dataset.statusTitle || 'Не выбрано';
        }

        function restoreConfirmedStatus() {
            statusInputs.forEach(input => {
                input.checked = input === confirmedStatusInput;
            });
        }

        function hideStatusConfirm() {
            pendingStatusInput = null;
            statusConfirm?.classList.add('hidden');
            statusGrid?.classList.remove('hidden');
        }

        function showStatusConfirm(input) {
            pendingStatusInput = input;
            restoreConfirmedStatus();
            if (statusConfirmText) {
                statusConfirmText.textContent = `Вы выбрали: ${input.dataset.statusTitle || input.value}.`;
            }
            statusGrid?.classList.add('hidden');
            statusConfirm?.classList.remove('hidden');
        }

        function openStatusModal() {
            if (!statusModal) return;
            hideStatusConfirm();
            restoreConfirmedStatus();
            statusModal.classList.remove('hidden');
            statusModal.setAttribute('aria-hidden', 'false');
            statusGrid?.scrollTo({ top: 0 });
            console.debug('[operator-status-modal] opened', {
                optionCount: statusModal.querySelectorAll('input[name="operator_status"]').length,
            });
        }

        function closeStatusModal() {
            if (!statusModal) return;
            hideStatusConfirm();
            restoreConfirmedStatus();
            statusModal.classList.add('hidden');
            statusModal.setAttribute('aria-hidden', 'true');
            console.debug('[operator-status-modal] closed');
        }

        function syncAzimuthField() {
            const selected = document.querySelector('input[name="operator_wave_direction"]:checked')?.value;
            azimuthField.classList.toggle('hidden', selected !== 'azimuth');
        }

        statusPickerButton?.addEventListener('click', openStatusModal);
        statusClose?.addEventListener('click', closeStatusModal);
        statusModal?.addEventListener('click', event => {
            if (event.target === statusModal) closeStatusModal();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeStatusModal();
        });

        statusInputs.forEach(input => input.addEventListener('change', () => {
            if (applyingConfirmedStatus) {
                syncWarningField();
                syncStatusCurrent();
                return;
            }

            showStatusConfirm(input);
        }));
        statusConfirmApply?.addEventListener('click', () => {
            if (!pendingStatusInput) return;

            applyingConfirmedStatus = true;
            pendingStatusInput.checked = true;
            confirmedStatusInput = pendingStatusInput;
            syncWarningField();
            syncStatusCurrent();
            applyingConfirmedStatus = false;
            closeStatusModal();
        });
        statusConfirmCancel?.addEventListener('click', () => {
            restoreConfirmedStatus();
            hideStatusConfirm();
        });
        directionInputs.forEach(input => input.addEventListener('change', syncAzimuthField));

        async function runOperatorAction(button, url, pendingText) {
            const initialText = button.textContent;
            button.disabled = true;
            button.textContent = pendingText;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.error || data.message || 'Действие недоступно');
                }

                button.textContent = data.message || 'Готово';

                if (data.dwd_debug_summary && console.groupCollapsed) {
                    console.groupCollapsed('модель данных debug summary');
                    console.table(data.dwd_debug_summary);
                    console.groupEnd();
                }
            } catch (error) {
                button.textContent = error.message || 'Ошибка';
            } finally {
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = initialText;
                }, 2500);
            }
        }

        forceFetchButton?.addEventListener('click', () => {
            runOperatorAction(forceFetchButton, '/api/force-fetch', 'Загрузка...');
        });

        toggleParsingButton?.addEventListener('click', () => {
            runOperatorAction(toggleParsingButton, '/api/toggle-parsing', 'Переключение...');
        });

        function showPasswordMessage(message, type = 'error') {
            passwordMessage.textContent = message;
            passwordMessage.classList.remove('hidden', 'success', 'error');
            passwordMessage.classList.add(type);
        }

        function closePasswordModal() {
            passwordModal?.classList.add('hidden');
            passwordForm?.reset();
            passwordMessage?.classList.add('hidden');
        }

        passwordButton?.addEventListener('click', () => {
            passwordModal?.classList.remove('hidden');
            document.getElementById('current-password')?.focus();
        });

        passwordClose?.addEventListener('click', closePasswordModal);

        passwordModal?.addEventListener('click', (event) => {
            if (event.target === passwordModal) {
                closePasswordModal();
            }
        });

        function closeOperatorLogoutConfirmModal() {
            operatorLogoutConfirmModal?.classList.add('hidden');
        }

        operatorLogoutButton?.addEventListener('click', () => {
            operatorLogoutConfirmModal?.classList.remove('hidden');
        });

        operatorLogoutConfirmClose?.addEventListener('click', closeOperatorLogoutConfirmModal);
        operatorLogoutCancelButton?.addEventListener('click', closeOperatorLogoutConfirmModal);
        operatorLogoutConfirmModal?.addEventListener('click', (event) => {
            if (event.target === operatorLogoutConfirmModal) {
                closeOperatorLogoutConfirmModal();
            }
        });

        operatorLogoutConfirmButton?.addEventListener('click', async () => {
            operatorLogoutConfirmButton.disabled = true;

            try {
                const response = await fetch('/api/operator/logout', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Logout failed');
                }

                window.location.href = '/';
            } catch (error) {
                alert('Не удалось выйти из режима оператора.');
                operatorLogoutConfirmButton.disabled = false;
            }
        });

        passwordForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(passwordForm);
            const newPassword = formData.get('password');
            const confirmation = formData.get('password_confirmation');

            if (newPassword !== confirmation) {
                showPasswordMessage('Подтверждение нового пароля не совпадает.');
                return;
            }

            passwordSubmit.disabled = true;
            passwordSubmit.textContent = 'Сохранение...';
            passwordMessage.classList.add('hidden');

            try {
                const response = await fetch('/api/operator/password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        current_password: formData.get('current_password'),
                        password: newPassword,
                        password_confirmation: confirmation,
                    }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Не удалось сменить пароль.');
                }

                showPasswordMessage(data.message || 'Пароль изменён.', 'success');
                passwordForm.reset();
            } catch (error) {
                showPasswordMessage(error.message || 'Не удалось сменить пароль.');
            } finally {
                passwordSubmit.disabled = false;
                passwordSubmit.textContent = 'Сменить пароль';
            }
        });

        timerButton.addEventListener('click', () => {
            if (!timerStart) {
                timerStart = Date.now();
                timerButton.textContent = 'Остановить замер';
                timerNote.textContent = 'Идет замер времени между 10 волнами.';
                return;
            }

            const elapsedSeconds = (Date.now() - timerStart) / 1000;
            const period = Math.max(2, Math.min(12, Math.round(elapsedSeconds / 10)));
            periodSelect.value = String(period);
            timerStart = null;
            timerButton.textContent = 'Запустить замер';
            timerNote.textContent = `Период рассчитан: ${period} сек.`;
        });

        syncWarningField();
        syncStatusCurrent();
        syncAzimuthField();
    </script>
</body>
</html>
