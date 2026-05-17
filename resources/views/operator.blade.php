<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель оператора</title>
    @vite(['resources/css/app.css'])
</head>
<body class="operator-page">
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

            <form method="POST" action="/operator/{{ $beach->id }}" class="operator-form">
                @csrf
                <div class="operator-status-grid">
                    @for($level = 0; $level <= 5; $level++)
                        <label class="operator-status-option">
                            <input
                                type="radio"
                                name="operator_status"
                                value="{{ $level }}"
                                @checked((string) $beach->operator_status === (string) $level)
                            >
                            <span>
                                <img src="{{ asset('значки и иконки/operator-simbols/' . $level . '.png') }}" alt="">
                                <b>{{ $level }}</b>
                            </span>
                        </label>
                    @endfor

                    <label class="operator-status-option operator-status-hazard">
                        <input
                            type="radio"
                            name="operator_status"
                            value="hazard"
                            @checked($beach->operator_status === 'hazard')
                        >
                        <span>
                            <img src="{{ asset('значки и иконки/operator-simbols/hazard.png') }}" alt="">
                            <b>Опасность</b>
                        </span>
                    </label>
                </div>

                @error('operator_status')
                    <div class="operator-error">{{ $message }}</div>
                @enderror

                <button type="submit" class="operator-save-button">Сохранить</button>
            </form>
        </section>
    </main>
</body>
</html>
