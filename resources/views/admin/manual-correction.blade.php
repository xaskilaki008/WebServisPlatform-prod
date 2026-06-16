<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ручная корректировка</title>
    @vite(['resources/css/app.css'])
</head>
<body class="admin-page">
    <main class="admin-shell">
        <section class="admin-card">
            <header class="admin-header">
                <div>
                    <p class="admin-kicker">manual correction</p>
                    <h1>Ручная корректировка</h1>
                </div>
                <div class="admin-header-actions">
                    <a class="admin-nav-button admin-home-button" href="/" aria-label="Вернуться на сайт"></a>
                    <form method="POST" action="/admin/logout" onsubmit="return confirm('Выйти из панели администратора?')">
                        @csrf
                        <button type="submit" class="action-button secondary">Выйти</button>
                    </form>
                </div>
            </header>

            @include('admin.partials.nav', ['active' => 'manual'])

            @if(session('status'))
                <div class="admin-flash">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="admin-flash error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-flash error">{{ $errors->first() }}</div>
            @endif

            <section class="admin-section">
                <h2>Пляжи для корректировки</h2>
                <p class="admin-note">Администратор может внести ручную корректировку для любого пляжа. Эти данные будут отображаться как операторские данные до окончания срока актуальности.</p>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Автоматический уровень</th>
                                <th>Операторский статус</th>
                                <th>Срок актуальности</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($beaches as $beach)
                                <tr>
                                    <td>{{ $beach->id }}</td>
                                    <td><strong>{{ $beach->name }}</strong></td>
                                    <td>{{ $beach->wave_level }} / {{ $beach->category_label }}</td>
                                    <td>{{ $beach->operator_status_text ?? 'нет свежих данных' }}</td>
                                    <td>{{ $beach->operator_expires_at ?? '-' }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="action-button danger manual-edit-button"
                                            data-beach-id="{{ $beach->id }}"
                                            data-beach-name="{{ $beach->name }}"
                                            data-operator-status="{{ $beach->operator_status }}"
                                            data-operator-warning="{{ $beach->operator_warning }}"
                                            data-operator-wave-direction="{{ $beach->operator_wave_direction }}"
                                            data-operator-wave-azimuth="{{ $beach->operator_wave_azimuth }}"
                                            data-operator-wave-period="{{ $beach->operator_wave_period }}"
                                            data-operator-access-status="{{ $beach->operator_access_status }}"
                                        >
                                            Редактировать
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $beaches->links() }}
            </section>
        </section>
    </main>

    <div class="admin-modal-overlay" id="manual-correction-modal" hidden>
        <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="manual-correction-title">
            <div class="admin-modal-header">
                <div>
                    <p class="admin-kicker">manual correction</p>
                    <h2 id="manual-correction-title">Ручная корректировка</h2>
                    <p class="admin-note" id="manual-correction-beach-name">Пляж не выбран</p>
                </div>
                <button type="button" class="action-button secondary admin-modal-close" id="manual-correction-close">Закрыть</button>
            </div>

            <form method="POST" action="#" class="admin-modal-form" id="manual-correction-form">
                @csrf
                <label>
                    Состояние моря
                    <select name="operator_status" id="manual-operator-status" required>
                        <option value="0">0 · Зеркально-гладкая</option>
                        <option value="1">1 · Рябь</option>
                        <option value="2">2 · Появляются небольшие гребни волн</option>
                        <option value="3">3 · Небольшие гребни волн начинают опрокидываться</option>
                        <option value="4">4 · Местами появляются "барашки"</option>
                        <option value="5">5 · Повсюду образуются "барашки"</option>
                        <option value="6">6 · Ветер срывает пену с гребней</option>
                        <option value="hazard">Особое предупреждение</option>
                    </select>
                </label>

                <label>
                    Особое предупреждение
                    <textarea name="operator_warning" id="manual-operator-warning" rows="3" maxlength="250" placeholder="Необязательно"></textarea>
                </label>

                <label>
                    Направление волн
                    <select name="operator_wave_direction" id="manual-operator-wave-direction" required>
                        <option value="direct">Прямо на пляж</option>
                        <option value="left">Слева на пляж</option>
                        <option value="right">Справа на пляж</option>
                        <option value="azimuth">Под конкретным направлением</option>
                        <option value="chaotic">Не определить (толчея)</option>
                    </select>
                </label>

                <label>
                    Азимут, если выбран конкретный угол
                    <input type="number" name="operator_wave_azimuth" id="manual-operator-wave-azimuth" min="0" max="360" placeholder="0-360">
                </label>

                <label>
                    Период волн
                    <select name="operator_wave_period" id="manual-operator-wave-period" required>
                        @for($seconds = 2; $seconds <= 12; $seconds++)
                            <option value="{{ $seconds }}">{{ $seconds }} сек</option>
                        @endfor
                    </select>
                </label>

                <label>
                    Доступность пляжа
                    <select name="operator_access_status" id="manual-operator-access-status" required>
                        <option value="open">Пляж открыт для всех</option>
                        <option value="limited">Пляж ограниченно открыт</option>
                        <option value="closed">Пляж полностью закрыт для купания</option>
                    </select>
                </label>

                <label>
                    Срок актуальности
                    <select name="operator_validity" id="manual-operator-validity" required>
                        <option value="30m">30 минут</option>
                        <option value="1h" selected>1 час</option>
                        <option value="3h">3 часа</option>
                        <option value="24h">24 часа</option>
                        <option value="until_disabled">До ручного отключения</option>
                    </select>
                </label>

                <div class="admin-modal-actions">
                    <button type="submit" class="action-button primary">Сохранить</button>
                </div>
            </form>

            <form method="POST" action="#" id="manual-correction-reset-form" class="admin-modal-reset">
                @csrf
                <button type="submit" class="action-button danger">Удалить ручную корректировку</button>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('manual-correction-modal');
            const form = document.getElementById('manual-correction-form');
            const resetForm = document.getElementById('manual-correction-reset-form');
            const closeButton = document.getElementById('manual-correction-close');
            const beachName = document.getElementById('manual-correction-beach-name');
            const fields = {
                status: document.getElementById('manual-operator-status'),
                warning: document.getElementById('manual-operator-warning'),
                direction: document.getElementById('manual-operator-wave-direction'),
                azimuth: document.getElementById('manual-operator-wave-azimuth'),
                period: document.getElementById('manual-operator-wave-period'),
                access: document.getElementById('manual-operator-access-status'),
                validity: document.getElementById('manual-operator-validity'),
            };

            if (!modal || !form || !resetForm) return;

            const setValue = (field, value, fallback) => {
                if (!field) return;
                field.value = value || fallback;
            };

            const closeModal = () => {
                modal.hidden = true;
                document.body.classList.remove('admin-modal-open');
            };

            document.querySelectorAll('.manual-edit-button').forEach((button) => {
                button.addEventListener('click', () => {
                    const id = button.dataset.beachId;
                    form.action = `/admin/manual-correction/${id}`;
                    resetForm.action = `/admin/manual-correction/${id}/reset`;
                    beachName.textContent = button.dataset.beachName || 'Пляж не выбран';

                    setValue(fields.status, button.dataset.operatorStatus, '0');
                    setValue(fields.warning, button.dataset.operatorWarning, '');
                    setValue(fields.direction, button.dataset.operatorWaveDirection, 'chaotic');
                    setValue(fields.azimuth, button.dataset.operatorWaveAzimuth, '');
                    setValue(fields.period, button.dataset.operatorWavePeriod, '6');
                    setValue(fields.access, button.dataset.operatorAccessStatus, 'open');
                    setValue(fields.validity, '', '1h');

                    modal.hidden = false;
                    document.body.classList.add('admin-modal-open');
                });
            });

            closeButton?.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
