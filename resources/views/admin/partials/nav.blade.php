@php($active = $active ?? '')

<nav class="admin-nav" aria-label="Навигация администратора">
    <a class="admin-nav-button" href="/" data-admin-tooltip="Открыть публичную карту и карточки пляжей.">Вернуться на сайт</a>
    <a class="admin-nav-button {{ $active === 'dashboard' ? 'active' : '' }}" href="/admin" data-admin-tooltip="Основная сводка, состояние парсинга и диагностика модели данных.">Панель администратора</a>
    <a class="admin-nav-button {{ $active === 'beaches' ? 'active' : '' }}" href="/admin/beaches" data-admin-tooltip="Список пляжей, координаты и текущие категории волнения.">Управление пляжами</a>
    <a class="admin-nav-button {{ $active === 'operators' ? 'active' : '' }}" href="/admin/operators" data-admin-tooltip="Операторы и закрепление операторов за одним или несколькими пляжами.">Управление операторами</a>
    <a class="admin-nav-button {{ $active === 'users' ? 'active' : '' }}" href="/admin/users" data-admin-tooltip="Пользователи, роли, активность аккаунтов и административные ограничения.">Пользователи и роли</a>
    <a class="admin-nav-button {{ $active === 'logs' ? 'active' : '' }}" href="/admin/action-logs" data-admin-tooltip="Журнал входов, изменений, действий операторов и администраторов.">Журнал действий</a>
    <a class="admin-nav-button {{ $active === 'manual' ? 'active' : '' }}" href="/admin/manual-correction" data-admin-tooltip="Ручная корректировка данных по любому пляжу от имени администратора.">Ручная корректировка</a>
</nav>

@include('admin.partials.suspicious-users')
