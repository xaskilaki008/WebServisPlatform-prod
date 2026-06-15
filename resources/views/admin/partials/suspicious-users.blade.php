@if(($suspiciousUsers ?? collect())->isNotEmpty())
    <section class="admin-suspicious-panel" aria-label="Высокая активность пользователей">
        <div>
            <p class="admin-kicker">activity guard</p>
            <h2>Слишком высокая активность</h2>
            <p class="admin-note">Пользователи ниже совершили много действий за последний час. Проверьте журнал и при необходимости заблокируйте аккаунт.</p>
        </div>
        <div class="admin-suspicious-list">
            @foreach($suspiciousUsers as $suspiciousUser)
                <article class="admin-suspicious-item">
                    <div>
                        <strong>{{ $suspiciousUser->full_name ?: $suspiciousUser->login ?: $suspiciousUser->email ?: ('Пользователь #' . $suspiciousUser->id) }}</strong>
                        <span>{{ $suspiciousUser->role?->name ?? 'без роли' }} · {{ $suspiciousUser->recent_action_count }} действий за час</span>
                    </div>
                    <form method="POST" action="/admin/users/{{ $suspiciousUser->id }}/ban">
                        @csrf
                        <button type="submit" class="action-button danger">Забанить</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endif
