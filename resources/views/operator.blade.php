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
            '0' => ['title' => 'Зеркальный штиль', 'note' => 'Все отлично'],
            '1' => ['title' => 'Легкая рябь', 'note' => 'Все отлично'],
            '2' => ['title' => 'Небольшое волнение', 'note' => 'Нужна осторожность'],
            '3' => ['title' => 'Умеренное волнение', 'note' => 'Нужна осторожность'],
            '4' => ['title' => 'Крупные волны', 'note' => 'Купание запрещено'],
            '5' => ['title' => 'Сильные волны', 'note' => 'Купание запрещено'],
            'hazard' => ['title' => 'Особая опасность', 'note' => 'Оперативное предупреждение'],
        ];
    @endphp

    <main class="operator-shell">
        <section class="operator-card">
            <header class="operator-header">
                <div>
                    <p class="operator-kicker">Пляж ID {{ $beach->id }}</p>
                    <h1>{{ $beach->name }}</h1>
                </div>
                <a class="operator-back-link" href="/">Карта</a>
            </header>

            @if(session('status'))
                <div class="operator-flash">{{ session('status') }}</div>
            @endif

            <div class="operator-readonly">
                <label>Название пляжа</label>
                <div>{{ $beach->name }}</div>
                <label>Внешний прогноз</label>
                <div>
                    Высота волны: {{ $forecast?->wave_height ?? 'нет данных' }} м;
                    период: {{ $forecast?->wave_period ?? 'нет данных' }} сек.
                </div>
            </div>

            <form method="POST" action="/operator/{{ $beach->id }}" class="operator-form">
                @csrf

                <fieldset class="operator-fieldset">
                    <legend>Состояние моря</legend>
                    <div class="operator-status-grid">
                        @foreach($statusOptions as $value => $option)
                            <label class="operator-status-option">
                                <input
                                    type="radio"
                                    name="operator_status"
                                    value="{{ $value }}"
                                    @checked((string) $beach->operator_status === (string) $value)
                                >
                                <span>
                                    <img src="{{ asset('значки и иконки/operator-simbols/' . ($value === 'hazard' ? 'hazard' : $value) . '.png') }}" alt="">
                                    <b>{{ $option['title'] }}</b>
                                    <small>{{ $option['note'] }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label id="operator-warning-field" class="operator-textarea-field hidden">
                    <span>Текст особого предупреждения</span>
                    <textarea name="operator_warning" maxlength="250" rows="3">{{ old('operator_warning', $beach->operator_warning) }}</textarea>
                </label>

                <fieldset class="operator-fieldset">
                    <legend>Направление волн</legend>
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
                    <legend>Период волн</legend>
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
                    <legend>Доступность пляжа</legend>
                    <select name="operator_access_status">
                        <option value="open" @selected(old('operator_access_status', $beach->operator_access_status ?? 'open') === 'open')>Пляж открыт для всех</option>
                        <option value="limited" @selected(old('operator_access_status', $beach->operator_access_status) === 'limited')>Пляж ограниченно открыт</option>
                        <option value="closed" @selected(old('operator_access_status', $beach->operator_access_status) === 'closed')>Пляж полностью закрыт для купания</option>
                    </select>
                </fieldset>

                @if($errors->any())
                    <div class="operator-error">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="operator-save-button">Сохранить и опубликовать</button>
            </form>
        </section>
    </main>

    <script>
        const statusInputs = document.querySelectorAll('input[name="operator_status"]');
        const warningField = document.getElementById('operator-warning-field');
        const directionInputs = document.querySelectorAll('input[name="operator_wave_direction"]');
        const azimuthField = document.getElementById('operator-azimuth-field');
        const timerButton = document.getElementById('operator-period-timer');
        const timerNote = document.getElementById('operator-timer-note');
        const periodSelect = document.getElementById('operator-wave-period');
        let timerStart = null;

        function syncWarningField() {
            const selected = document.querySelector('input[name="operator_status"]:checked')?.value;
            const shouldShow = selected === 'hazard' || Number(selected) >= 2;
            warningField.classList.toggle('hidden', !shouldShow);
        }

        function syncAzimuthField() {
            const selected = document.querySelector('input[name="operator_wave_direction"]:checked')?.value;
            azimuthField.classList.toggle('hidden', selected !== 'azimuth');
        }

        statusInputs.forEach(input => input.addEventListener('change', syncWarningField));
        directionInputs.forEach(input => input.addEventListener('change', syncAzimuthField));

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
        syncAzimuthField();
    </script>
</body>
</html>
