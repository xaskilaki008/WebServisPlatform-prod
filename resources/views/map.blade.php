<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мониторинг пляжей Севастополя</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <style>
        :root {
            color-scheme: light;
            --bg: #edf3f8;
            --surface: #ffffff;
            --surface-soft: #f6f9fc;
            --surface-strong: #0f2d44;
            --border: #d2deea;
            --text: #143149;
            --text-soft: #547086;
            --accent: #0e81c6;
            --accent-strong: #0b669c;
            --status-safe: #1f9d5a;
            --status-caution: #db9c08;
            --status-danger: #db4a40;
            --shadow-soft: 0 10px 30px rgba(21, 48, 72, 0.08);
            --shadow-strong: 0 20px 42px rgba(8, 30, 48, 0.18);
            --radius-xl: 22px;
            --radius-lg: 16px;
            --radius-md: 12px;
            --transition: 220ms ease;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: radial-gradient(circle at 8% -20%, #fdfefe, var(--bg) 65%);
            color: var(--text);
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
        }

        body {
            padding: 0;
        }

        .app-shell {
            width: 100%;
            min-height: 100vh;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            border-bottom: 1px solid rgba(210, 222, 234, 0.85);
            background: rgba(246, 250, 254, 0.92);
            backdrop-filter: blur(10px);
        }

        .topbar-inner {
            max-width: 1440px;
            margin: 0 auto;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .topbar-title-wrap {
            min-width: 0;
        }

        .topbar-title {
            margin: 0;
            font-size: clamp(18px, 2.3vw, 25px);
            letter-spacing: 0.01em;
        }

        .topbar-subtitle {
            margin: 2px 0 0;
            color: #173042;
            font-size: 13px;
        }

        .topbar-nav {
            display: grid;
            grid-template-columns: repeat(2, minmax(110px, 168px));
            gap: 8px;
        }

        .page-body {
            max-width: 1440px;
            margin: 0 auto;
            padding: 14px 12px 24px;
        }

        .screen {
            display: none;
        }

        .screen.active {
            display: block;
        }

        .nav-button,
        .action-button,
        .back-button,
        .detail-geo-button,
        .filter-chip,
        .map-control-button,
        .clear-search-button {
            border: none;
            border-radius: 11px;
            font: inherit;
            cursor: pointer;
            transition: background var(--transition), color var(--transition), border-color var(--transition), transform var(--transition), box-shadow var(--transition), opacity var(--transition);
        }

        .nav-button {
            padding: 10px 12px;
            background: #fff;
            color: var(--text);
            box-shadow: inset 0 0 0 1px var(--border);
            font-weight: 650;
        }

        .nav-button.active {
            background: var(--surface-strong);
            color: #fff;
            box-shadow: none;
        }

        .nav-button:focus-visible,
        .action-button:focus-visible,
        .back-button:focus-visible,
        .detail-geo-button:focus-visible,
        .filter-chip:focus-visible,
        .search-input:focus-visible,
        .map-control-button:focus-visible,
        .clear-search-button:focus-visible {
            outline: 2px solid rgba(15, 131, 203, 0.48);
            outline-offset: 2px;
        }

        .map-layout {
            display: grid;
            gap: 14px;
        }

        .left-column {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .panel,
        .list-card,
        .detail-card,
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .panel,
        .detail-card,
        .filter-panel,
        .list-card {
            padding: 15px;
        }

        .panel h2,
        .panel h3,
        .detail-card h2,
        .screen-title,
        .filter-title {
            margin: 0 0 10px;
            font-size: 17px;
        }

        .panel p,
        .detail-field,
        .list-meta,
        .screen-subtitle,
        .filter-description,
        .info-note {
            margin: 5px 0;
            color: var(--text-soft);
            line-height: 1.45;
            font-size: 14px;
        }

        .panel strong,
        .detail-field strong,
        .list-meta strong,
        .info-note strong {
            color: var(--text);
        }

        .info-grid {
            display: grid;
            gap: 7px;
        }

        .info-row {
            display: grid;
            grid-template-columns: minmax(130px, 148px) 1fr;
            gap: 8px;
            font-size: 14px;
            line-height: 1.4;
        }

        .info-row b {
            color: var(--text);
            font-weight: 650;
        }

        .category-inline {
            display: inline-flex;
            margin-top: 2px;
        }

        .legend-panel {
            overflow: hidden;
        }

        .legend-meta {
            margin: 0 0 10px;
            font-size: 13px;
            color: #0b669c;
        }

        .legend-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            display: block;
            border-radius: var(--radius-md);
            background: var(--surface-soft);
        }

        .map-card {
            min-width: 0;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow-strong);
            background: var(--surface);
            transition: width var(--transition), box-shadow var(--transition), transform var(--transition);
            position: relative;
        }

        .map-toolbar {
            position: absolute;
            z-index: 900;
            /* Опускаем тулбар ниже стандартных кнопок масштаба Leaflet */
            top: 90px; 
            right: 10px;
            /* Убираем left, чтобы тулбар не растягивался на всю ширину */
            left: auto; 
            
            display: flex;
            /* Выстраиваем элементы в колонку */
            flex-direction: column; 
            align-items: flex-end;
            gap: 8px;
            pointer-events: none;
        }

        /* Убираем лишние отступы у групп, если они мешают */
        .map-toolbar-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: auto;
        }

        .map-toolbar-group {
            display: flex;
            gap: 8px;
            pointer-events: auto;
        }

        .map-control-button {
            padding: 8px 10px;
            min-height: 40px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text);
            border: 1px solid rgba(210, 222, 234, 0.95);
            box-shadow: 0 6px 14px rgba(13, 40, 61, 0.16);
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
        }

        .map-control-button:hover {
            transform: translateY(-1px);
        }

        #map {
            width: 100%;
            height: clamp(360px, 62vh, 860px);
            transition: height var(--transition);
        }

        .screen-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .detail-header-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .filter-panel {
            margin-bottom: 12px;
        }

        .search-row {
            display: grid;
            gap: 8px;
            margin: 10px 0 10px;
        }

        .search-input {
            width: 100%;
            padding: 11px 13px;
            min-height: 44px;
            border-radius: 11px;
            border: 1px solid var(--border);
            font: inherit;
            color: var(--text);
        }

        .clear-search-button {
            padding: 10px 14px;
            min-height: 44px;
            background: var(--surface-soft);
            color: var(--text);
            border: 1px solid var(--border);
            font-weight: 650;
            justify-self: start;
        }

        .clear-search-button:hover {
            background: #ecf2f8;
        }

        .filter-chips,
        .list-wrap,
        .detail-fields {
            display: grid;
            gap: 10px;
        }

        .filter-panel {
            position: sticky;
            top: var(--filter-sticky-top, 78px);
            z-index: 30;
        }

        .filter-chip {
            min-height: 42px;
            padding: 10px 12px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            color: var(--text);
            font-weight: 650;
            text-align: left;
        }

        .filter-chip.active {
            background: #e3f1fb;
            border-color: #a9d4ef;
            color: #0a5f92;
        }

        .list-wrap {
            grid-template-columns: repeat(1, minmax(0, 1fr));
            overflow: visible;
        }

        .list-card {
            position: relative;
            display: grid;
            gap: 8px;
            min-height: 188px;
            isolation: isolate;
            overflow: visible;
            transition: box-shadow var(--transition), border-color var(--transition);
        }

        .list-card::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            border-radius: inherit;
            background:
                linear-gradient(115deg, transparent 0%, transparent 38%, rgba(255, 255, 255, 0.82) 50%, transparent 62%, transparent 100%),
                var(--surface);
            background-size: 240% 100%, 100% 100%;
            border: 1px solid transparent;
            opacity: 0;
            transform: scale(1);
            box-shadow: var(--shadow-soft);
            transition: transform 0.5s ease, opacity 0.2s ease, border-color var(--transition), box-shadow var(--transition);
        }

        .list-card:hover {
            border-color: #9fc4de;
            z-index: 5;
            box-shadow: none;
        }

        .list-card:hover::before {
            opacity: 1;
            transform: scale(1.2);
            border-color: #9fc4de;
            box-shadow: 0 18px 36px rgba(13, 40, 61, 0.2);
            animation: card-shimmer 0.5s ease;
        }

        .list-card.selected {
            border-color: #7db8df;
            box-shadow: 0 0 0 2px rgba(126, 182, 222, 0.3), var(--shadow-soft);
        }

        .list-card h3 {
            margin: 0;
            font-size: 16px;
            line-height: 1.35;
            width: fit-content;
            max-width: 100%;
            cursor: help;
            transform-origin: left center;
            transition: transform 0.5s ease, color var(--transition);
        }

        .list-card:hover h3 {
            transform: scale(1.18);
        }

        @keyframes card-shimmer {
            0% {
                background-position: 140% 0, 0 0;
            }
            100% {
                background-position: -80% 0, 0 0;
            }
        }

        .list-id {
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 600;
        }

        .list-actions {
            margin-top: 4px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .action-button,
        .back-button {
            min-height: 42px;
            padding: 9px 11px;
            font-weight: 650;
        }

        .action-button {
            background: #f5f8fb;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .action-button.primary,
        .back-button {
            background: var(--surface-strong);
            color: #fff;
            border: 1px solid transparent;
        }

        .action-button.primary:hover,
        .back-button:hover {
            background: #12364f;
        }

        .popup-actions {
            margin-top: 10px;
        }

        .popup-detail-button {
            width: 100%;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
            margin-top: 2px;
        }

        .badge-safe {
            background: rgba(31, 157, 90, 0.14);
            color: #0b6b3b;
        }

        .badge-caution {
            background: rgba(219, 156, 8, 0.18);
            color: #8e6100;
        }

        .badge-danger {
            background: rgba(219, 74, 64, 0.16);
            color: #a22d26;
        }

        .detail-card h2 {
            margin-bottom: 12px;
        }

        .detail-title-row {
            display: grid;
            grid-template-columns: minmax(42px, 1fr) auto minmax(42px, 1fr);
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .detail-title-row h2 {
            grid-column: 2;
            margin: 0;
            text-align: center;
            font-size: clamp(24px, 4vw, 36px);
            line-height: 1.15;
        }

        .detail-geo-wrap {
            grid-column: 3;
            justify-self: start;
            position: relative;
            display: inline-flex;
            align-items: center;
            width: max-content;
        }

        .detail-geo-button {
            width: 44px;
            height: 44px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            appearance: none;
            -webkit-appearance: none;
            background: transparent;
            border: none;
            box-shadow: none;
            outline: none;
        }

        .detail-geo-button img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            display: block;
            border-radius: 0;
            box-shadow: none;
            transition: transform var(--transition), opacity var(--transition);
        }

        .detail-geo-wrap:hover .detail-geo-button img {
            transform: translate(-8px, -1px);
            opacity: 0.9;
        }

        .detail-coordinates {
            position: absolute;
            left: 42px;
            top: 50%;
            transform: translateY(-50%) translateX(-6px);
            min-width: max-content;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #fff;
            color: var(--text);
            font-size: 13px;
            font-weight: 650;
            box-shadow: 0 10px 22px rgba(13, 40, 61, 0.18);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            cursor: pointer;
            transition: opacity var(--transition), transform var(--transition), visibility var(--transition);
            z-index: 10;
        }

        .detail-geo-wrap:hover .detail-coordinates {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(-50%) translateX(0);
        }

        .detail-fields {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .detail-actions {
            margin-top: 12px;
            display: flex;
            justify-content: flex-start;
        }

        .detail-field {
            background: var(--surface-soft);
            border: 1px solid #dce6f0;
            border-radius: 11px;
            padding: 10px 11px;
            margin: 0;
        }

        .counter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: #e2eef8;
            color: #29506c;
            margin-left: 6px;
        }

        .empty-state {
            padding: 18px;
            border-radius: var(--radius-lg);
            border: 1px dashed #bfd2e2;
            background: linear-gradient(180deg, #fbfdff 0%, #f3f9ff 100%);
            color: var(--text-soft);
            text-align: center;
        }

        .skeleton {
            background: linear-gradient(90deg, #e9f0f7 25%, #f6f9fc 50%, #e9f0f7 75%);
            background-size: 220% 100%;
            animation: loading 1.3s linear infinite;
        }

        .skeleton-card {
            border-radius: var(--radius-lg);
            min-height: 160px;
            border: 1px solid #dee8f2;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .scroll-top-button {
            position: fixed;
            right: 14px;
            bottom: 18px;
            width: 50px;
            height: 50px;
            border: none;
            border-radius: 50%;
            background: #173042;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(14, 34, 51, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(9px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 1200;
        }

        .scroll-top-button.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .leaflet-tooltip {
            border-radius: 8px;
            border: 1px solid #cedceb;
            box-shadow: 0 8px 18px rgba(12, 35, 52, 0.2);
            color: #123149;
            font-weight: 520;
        }

        .beach-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 3px 10px rgba(14, 37, 53, 0.32);
            transform: translate(-50%, -50%);
            transition: width var(--transition), height var(--transition), box-shadow var(--transition);
        }

        .beach-marker.selected {
            width: 26px;
            height: 26px;
            box-shadow: 0 0 0 6px rgba(9, 122, 188, 0.22), 0 5px 14px rgba(14, 37, 53, 0.42);
        }

        .beach-marker.safe {
            background: var(--status-safe);
        }

        .beach-marker.caution {
            background: var(--status-caution);
        }

        .beach-marker.danger {
            background: var(--status-danger);
        }

        #map-screen.is-map-expanded .map-layout {
            grid-template-columns: 1fr;
        }

        #map-screen.is-map-expanded .left-column {
            opacity: 0;
            visibility: hidden;
            max-height: 0;
            overflow: hidden;
            pointer-events: none;
            transition: opacity var(--transition), max-height var(--transition);
        }

        #map-screen.is-map-expanded .map-card {
            box-shadow: 0 26px 50px rgba(9, 29, 46, 0.25);
        }

        #map-screen.is-map-expanded #map {
            height: calc(100vh - 140px);
            min-height: 480px;
        }

        @media (min-width: 820px) {
            body {
                padding: 14px;
            }

            .app-shell {
                border-radius: 28px;
                overflow: hidden;
                box-shadow: 0 30px 50px rgba(12, 35, 55, 0.14);
            }

            .topbar-inner {
                padding: 16px 20px;
            }

            .page-body {
                padding: 16px 20px 28px;
            }

            .filter-chips {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .list-wrap {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .detail-fields {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
            }
        }

        @media (min-width: 1120px) {
            .map-layout {
                    /* Если удалил aside целиком, ставь 1fr. 
                    Если оставил легенду, можно оставить 320px или уменьшить */
                    grid-template-columns: 1fr; 
                    gap: 16px;
            }
            .left-column {
                position: sticky;
                top: 86px;
            }

            #map {
                height: calc(100vh - 170px);
                min-height: 650px;
            }

            #map-screen.is-map-expanded #map {
                height: calc(100vh - 124px);
                min-height: 700px;
            }

            .list-wrap {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 819px) {
            .topbar-inner {
                flex-direction: column;
                align-items: stretch;
            }

            .topbar-nav {
                width: 100%;
                grid-template-columns: 1fr 1fr;
            }

            .topbar-subtitle {
                font-size: 12px;
            }

            .screen-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .clear-search-button {
                width: 100%;
            }

            .filter-chips {
                grid-template-columns: 1fr;
            }

            .list-actions {
                grid-template-columns: 1fr;
            }

            .map-toolbar {
                right: 8px;
                left: 56px;
            }

            .map-control-button {
                min-height: 42px;
                padding: 8px 10px;
            }

            #map {
                height: min(62vh, 560px);
                min-height: 340px;
            }

            #map-screen.is-map-expanded #map {
                height: calc(100vh - 130px);
                min-height: 400px;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 2px;
            }
            .filter-panel {
                position: static; 
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
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
                    <!-- <div id="info-panel" class="panel">
                        <h2>Текущий пляж</h2>
                        <div class="info-grid">
                            <div class="info-row"><b>Название</b><span id="info-name">Загрузка...</span></div>
                            <div class="info-row"><b>Номер</b><span id="info-number">-</span></div>
                            <div class="info-row"><b>Уровень волнения</b><span id="info-wave-level">-</span></div>
                            <div class="info-row"><b>Описание</b><span id="info-wave-text">Нет данных</span></div>
                            <div class="info-row"><b>Категория</b><span id="info-category-badge" class="category-inline category-badge">-</span></div>
                        </div>
                    </div> -->

                    <div class="panel legend-panel">
                        <h3>Легенда статусов</h3>
                        <p class="legend-meta">Флажок и полигон связаны единым цветом категории безопасности.</p>
                        <img class="legend-image" src="{{ asset('./flag-colors.png') }}" alt="Цвета флажков">
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
            <article class="detail-card">
                <h2 id="detail-name">Пляж не выбран</h2>
                <div class="detail-fields">
                    <div class="detail-field"><strong>Номер:</strong> <span id="detail-number">-</span></div>
                    <div class="detail-field"><strong>Уровень волнения:</strong> <span id="detail-wave-level">-</span></div>
                    <div class="detail-field"><strong>Описание волнения:</strong> <span id="detail-wave-text">Нет данных</span></div>
                    <div class="detail-field"><strong>Категория:</strong> <span id="detail-category">-</span></div>
                </div>
            </article>
        </section>
    </main>
</div>

<button id="scroll-top-button" class="scroll-top-button" type="button">↑</button>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
<script>
    const topbar = document.querySelector('.topbar');
    const mapScreen = document.getElementById('map-screen');
    const mapElement = document.getElementById('map');
    const map = L.map(mapElement).setView([44.61, 33.52], 11);
    map.zoomControl.setPosition('topright');
    map.createPane('beachPolygonsPane');
    map.getPane('beachPolygonsPane').style.zIndex = '350';
    map.getPane('beachPolygonsPane').style.pointerEvents = 'auto';

    // const infoName = document.getElementById('info-name');
    // const infoNumber = document.getElementById('info-number');
    // const infoWaveLevel = document.getElementById('info-wave-level');
    // const infoWaveText = document.getElementById('info-wave-text');
    // const infoCategoryBadge = document.getElementById('info-category-badge');

    const beachesList = document.getElementById('beaches-list');
    const resultsCounter = document.getElementById('results-counter');
    const detailName = document.getElementById('detail-name');
    const detailNumber = document.getElementById('detail-number');
    const detailWaveLevel = document.getElementById('detail-wave-level');
    const detailWaveText = document.getElementById('detail-wave-text');
    const detailCategory = document.getElementById('detail-category');
    const detailBackButton = document.getElementById('detail-back-button');
    const detailTitleRow = document.createElement('div');
    const detailGeoWrap = document.createElement('div');
    const detailGeoButton = document.createElement('button');
    const detailCoordinates = document.createElement('button');
    const detailHeaderActions = document.createElement('div');
    const detailReturnButton = document.createElement('button');
    const detailMapButton = document.createElement('button');
    const navButtons = document.querySelectorAll('[data-screen-target]');
    const screens = document.querySelectorAll('.screen');
    const searchInput = document.getElementById('search-input');
    const filterChips = document.querySelectorAll('[data-category]');
    const scrollTopButton = document.getElementById('scroll-top-button');
    const clearSearchButton = document.getElementById('clear-search-button');
    const toggleMapSizeButton = document.getElementById('toggle-map-size-button');
    const fitMapButton = document.getElementById('fit-map-button');

    const cssVariables = getComputedStyle(document.documentElement);
    const polygonColors = {
        safe: cssVariables.getPropertyValue('--status-safe').trim(),
        caution: cssVariables.getPropertyValue('--status-caution').trim(),
        danger: cssVariables.getPropertyValue('--status-danger').trim()
    };
    const polygonRelatedRadiusKm = 0.2;

    const beaches = [];
    const markersById = new Map();
    const polygonLayers = [];
    let beachesPolygonLayer = null;
    let selectedBeach = null;
    let lastNonDetailScreen = 'map-screen';
    let detailReturnScreen = 'list-screen';
    let activeCategory = 'all';
    let searchQuery = '';
    let isMapExpanded = false;
    let mapFocusRequestId = 0;

    function getWaveLevelText(level) {
        const numericLevel = Number(level);
        if (Number.isNaN(numericLevel)) return 'Нет данных';
        if (numericLevel === 0) return 'Слабое волнение';
        if (numericLevel <= 2) return 'Небольшое волнение';
        if (numericLevel <= 4) return 'Умеренное волнение';
        if (numericLevel <= 6) return 'Заметное волнение';
        if (numericLevel <= 9) return 'Сильное волнение';
        return 'Очень сильное волнение';
    }

    function getBeachCategoryKey(beach) {
        if (beach && beach.category_key) return beach.category_key;
        const level = Number(beach?.wave_level);
        if (Number.isNaN(level)) return 'danger';
        if (level <= 2) return 'safe';
        if (level <= 5) return 'caution';
        return 'danger';
    }

    function getBeachCategoryLabel(beach) {
        if (beach && beach.category_label) return beach.category_label;
        const key = getBeachCategoryKey(beach || {});
        if (key === 'safe') return 'Купание допустимо';
        if (key === 'caution') return 'Нужна осторожность';
        return 'Купание не рекомендуется';
    }

    function getCategoryBadgeClass(beach) {
        const key = getBeachCategoryKey(beach);
        if (key === 'safe') return 'badge-safe';
        if (key === 'caution') return 'badge-caution';
        return 'badge-danger';
    }

    function renderLoadingState() {
        beachesList.innerHTML = `
            <div class="skeleton skeleton-card"></div>
            <div class="skeleton skeleton-card"></div>
            <div class="skeleton skeleton-card"></div>
        `;
    }

    function updateInfoPanel(beach = {}) {
        infoName.textContent = beach.name || 'Без названия';
        infoNumber.textContent = beach.number ?? '-';
        infoWaveLevel.textContent = beach.wave_level ?? '-';
        infoWaveText.textContent = getWaveLevelText(beach.wave_level);
        infoCategoryBadge.textContent = getBeachCategoryLabel(beach);
        infoCategoryBadge.className = 'category-inline category-badge ' + getCategoryBadgeClass(beach);
    }

    function updateDetailScreen(beach = {}) {
        detailName.textContent = beach.name || 'Без названия';
        detailNumber.textContent = beach.number ?? '-';
        detailWaveLevel.textContent = beach.wave_level ?? '-';
        detailWaveText.textContent = getWaveLevelText(beach.wave_level);
        detailCategory.textContent = getBeachCategoryLabel(beach);
        detailMapButton.dataset.id = beach.id ?? '';
        const hasCoordinates = beach.latitude !== undefined && beach.latitude !== null && beach.longitude !== undefined && beach.longitude !== null;
        detailCoordinates.textContent = hasCoordinates ? `${beach.latitude}, ${beach.longitude}` : '-';
        detailCoordinates.dataset.coordinates = hasCoordinates ? `${beach.latitude}, ${beach.longitude}` : '';
    }

    function requestMapResize() {
        [0, 140, 260, 420].forEach(delay => {
            window.setTimeout(() => map.invalidateSize(), delay);
        });
    }

    function setActiveScreen(screenId) {
        screens.forEach(screen => {
            screen.classList.toggle('active', screen.id === screenId);
        });

        navButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.screenTarget === screenId);
        });

        if (screenId !== 'detail-screen') {
            lastNonDetailScreen = screenId;
        }

        if (screenId === 'map-screen') {
            requestMapResize();
        }

        if (screenId === 'list-screen') {
            syncSearchStateFromInput();
            renderBeachesList();
        }
    }

    function getActiveScreenId() {
        const activeScreen = document.querySelector('.screen.active');
        return activeScreen ? activeScreen.id : 'map-screen';
    }

    function updateDetailBackButton() {
        detailReturnButton.textContent = detailReturnScreen === 'map-screen'
            ? '\u041f\u0435\u0440\u0435\u0439\u0442\u0438 \u043a \u043a\u0430\u0440\u0442\u0435 \u043f\u043b\u044f\u0436\u0435\u0439'
            : '\u041f\u0435\u0440\u0435\u0439\u0442\u0438 \u043a \u0441\u043f\u0438\u0441\u043a\u0443 \u043f\u043b\u044f\u0436\u0435\u0439';
    }

    function syncSearchStateFromInput() {
        searchQuery = searchInput.value.trim().toLowerCase();
    }

    function buildPopupContent(beach) {
        const categoryClass = getCategoryBadgeClass(beach);
        const categoryLabel = getBeachCategoryLabel(beach);
        
        return `
            <div style="min-width: 160px;">
                <b style="font-size: 14px;">${beach.name || 'Без названия'}</b><br>
                <div style="margin: 5px 0 8px 0;">
                    <span class="category-badge ${categoryClass}" style="font-size: 10px; padding: 4px 8px;">
                        ${categoryLabel}
                    </span>
                </div>
                <div style="font-size: 12px; line-height: 1.4;">
                    <b>Номер:</b> ${beach.number ?? '-'}<br>
                    <b>Волнение:</b> ${beach.wave_level ?? '-'} (${getWaveLevelText(beach.wave_level)})
                </div>
            </div>
        `;
    }

    function buildPolygonPopupContent(properties = {}) {
        const lines = [];
        const hasPrimaryData = properties.name ||
            (properties.number !== undefined && properties.number !== null && properties.number !== '') ||
            (properties.wave_level !== undefined && properties.wave_level !== null && properties.wave_level !== '') ||
            properties.category_label;

        if (properties.name) lines.push('<b>' + properties.name + '</b>');
        if (properties.number !== undefined && properties.number !== null && properties.number !== '') lines.push('Номер: ' + properties.number);
        if (properties.wave_level !== undefined && properties.wave_level !== null && properties.wave_level !== '') {
            lines.push('Уровень волнения: ' + properties.wave_level);
            lines.push('Описание: ' + getWaveLevelText(properties.wave_level));
        }
        if (hasPrimaryData) lines.push('Категория: ' + getBeachCategoryLabel(properties));
        return lines.join('<br>');
    }

    function getPolygonStyle(properties = {}, isSelected = false) {
        const categoryKey = getBeachCategoryKey(properties);
        const color = polygonColors[categoryKey] || polygonColors.danger;
        return {
            color: color,
            weight: isSelected ? 3 : 2,
            opacity: isSelected ? 1 : 0.95,
            fillColor: color,
            fillOpacity: isSelected ? 0.42 : 0.27
        };
    }

    function createMarkerIcon(categoryKey, isSelectedMarker) {
        return new L.Icon.Default();
    }

    function refreshMarkerStyles() {
        markersById.forEach((marker, beachId) => {
            const beach = beaches.find(item => item.id === beachId);
            if (!beach) return;
            const isSelectedMarker = Boolean(selectedBeach && selectedBeach.id === beach.id);
            marker.setZIndexOffset(isSelectedMarker ? 2000 : 1000);
        });
    }

    function getMapDataBounds() {
        let combinedBounds = null;
        markersById.forEach(marker => {
            const latLng = marker.getLatLng();
            const pointBounds = L.latLngBounds(latLng, latLng);
            combinedBounds = combinedBounds ? combinedBounds.extend(pointBounds) : pointBounds;
        });
        if (beachesPolygonLayer && beachesPolygonLayer.getLayers().length > 0) {
            const polygonBounds = beachesPolygonLayer.getBounds();
            if (polygonBounds.isValid()) {
                combinedBounds = combinedBounds ? combinedBounds.extend(polygonBounds) : polygonBounds;
            }
        }
        return combinedBounds;
    }

    function fitMapToAvailableData() {
        const bounds = getMapDataBounds();
        if (bounds && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }

    function getBeachPointFeature(beach) {
        const latitude = Number(beach.latitude);
        const longitude = Number(beach.longitude);
        if (Number.isNaN(latitude) || Number.isNaN(longitude)) return null;
        return turf.point([longitude, latitude]);
    }

    function getDistanceToPolygonKm(beachPoint, feature) {
        if (turf.booleanPointInPolygon(beachPoint, feature)) return 0;

        const polygonOutline = turf.polygonToLine(feature);
        const lineFeatures = turf.flatten(polygonOutline).features;

        return lineFeatures.reduce((minDistance, lineFeature) => {
            const distance = turf.pointToLineDistance(
                beachPoint,
                lineFeature,
                { units: 'kilometers' }
            );
            return Math.min(minDistance, distance);
        }, Number.POSITIVE_INFINITY);
    }

    function getBeachPolygonRelation(beach, feature) {
        const beachPoint = getBeachPointFeature(beach);
        if (!beachPoint || !feature || !feature.geometry) return null;

        try {
            const distanceToPolygonKm = getDistanceToPolygonKm(beachPoint, feature);
            if (!Number.isFinite(distanceToPolygonKm)) return null;

            return {
                beach: beach,
                distanceToPolygonKm: distanceToPolygonKm
            };
        } catch (error) {
            console.error('Ошибка сопоставления пляжа и полигона:', error);
            return null;
        }
    }

    function getRelatedBeachesForFeature(feature) {
        if (!beaches.length) return [];
        return beaches
            .map(beach => getBeachPolygonRelation(beach, feature))
            .filter(relation => relation && relation.distanceToPolygonKm <= polygonRelatedRadiusKm)
            .sort((a, b) => a.distanceToPolygonKm - b.distanceToPolygonKm)
            .map(relation => relation.beach);
    }

    function buildPolygonHoverContent(feature) {
        // Создаем аккуратный заголовок с линией, используя цвета из твоего CSS
        const header = '<div style="font-weight: 700; color: #0b669c;">Связанные пляжи</div><hr style="margin: 6px 0; border: none; border-top: 1px solid #cedceb;">';

        if (!beaches.length) return header + 'Данные пляжей еще загружаются';
        
        const relatedBeaches = getRelatedBeachesForFeature(feature);
        if (relatedBeaches.length === 0) return header + 'Пляжи не найдены';
        
        return header + relatedBeaches.map(beach => beach.name || 'Без названия').join('<br>');
    }

    function refreshPolygonStyles() {
        polygonLayers.forEach(entry => {
            const relatedBeaches = getRelatedBeachesForFeature(entry.feature);
            const source = relatedBeaches.length > 0 ? relatedBeaches[0] : (entry.feature.properties || {});
            const isSelectedPolygon = Boolean(selectedBeach && relatedBeaches.some(beach => beach.id === selectedBeach.id));
            entry.layer.setStyle(getPolygonStyle(source, isSelectedPolygon));
        });
    }

    function getFilteredBeaches() {
        return beaches.filter(beach => {
            const name = String(beach.name || '').toLowerCase();
            const matchesName = name.includes(searchQuery);
            const matchesCategory = activeCategory === 'all' || getBeachCategoryKey(beach) === activeCategory;
            return matchesName && matchesCategory;
        });
    }

    function refreshMarkerVisibility() {
        const visibleIds = new Set(getFilteredBeaches().map(beach => beach.id));
        markersById.forEach((marker, beachId) => {
            const isVisible = visibleIds.has(beachId);
            if (isVisible && !map.hasLayer(marker)) marker.addTo(map);
            if (!isVisible && map.hasLayer(marker)) map.removeLayer(marker);
        });
    }

    function renderBeachesList() {
        const filteredBeaches = getFilteredBeaches();
        resultsCounter.textContent = String(filteredBeaches.length);

        if (filteredBeaches.length === 0) {
            beachesList.innerHTML = '<div class="empty-state">По вашему запросу пляжи не найдены. Попробуйте снять фильтр или изменить текст поиска.</div>';
            refreshMarkerVisibility();
            return;
        }

        beachesList.innerHTML = filteredBeaches.map(beach => {
            const selectedClass = selectedBeach && selectedBeach.id === beach.id ? ' selected' : '';
            return `
                <article class="list-card${selectedClass}">
                    <div class="list-id">${beach.number ?? '-'}</div>
                    <h3>${beach.name || 'Без названия'}</h3>
                    <p class="list-meta"><strong>Волнение:</strong> ${beach.wave_level ?? '-'} (${getWaveLevelText(beach.wave_level)})</p>
                    <span class="category-badge ${getCategoryBadgeClass(beach)}">${getBeachCategoryLabel(beach)}</span>
                    <div class="list-actions">
                        <button type="button" class="action-button primary" data-action="show-on-map" data-id="${beach.id}">На карте</button>
                        <button type="button" class="action-button" data-action="show-details" data-id="${beach.id}">Подробно</button>
                    </div>
                </article>
            `;
        }).join('');

        beachesList.querySelectorAll('.list-card h3').forEach(title => {
            title.setAttribute('title', '\u043f\u0435\u0440\u0435\u0439\u0442\u0438 \u043a \u043f\u043b\u044f\u0436\u0443');
        });

        refreshMarkerVisibility();
    }

    function selectBeach(beach) {
        selectedBeach = beach;
        updateInfoPanel(beach);
        updateDetailScreen(beach);
        renderBeachesList();
        refreshMarkerStyles();
        refreshPolygonStyles();
    }

    function showSameBeachInfoAsMarker(beach) {
        selectBeach(beach);
        const marker = markersById.get(beach.id);
        if (!marker) return;
        if (!map.hasLayer(marker)) {
            marker.addTo(map);
        }
        openBeachPopup(marker, beach);
    }

    function renderMapMarkers() {
        beaches.forEach(beach => {
            const marker = L.marker(
                [beach.latitude, beach.longitude],
                { icon: createMarkerIcon(getBeachCategoryKey(beach), false) }
            )
                .bindPopup(buildPopupContent(beach))
                .addTo(map);

            marker.on('click', function () {
                selectBeach(beach);
            });

            marker.on('popupopen', function (event) {
                addDetailsButtonToPopup(event.popup, beach);
            });

            markersById.set(beach.id, marker);
        });

        fitMapToAvailableData();
        refreshMarkerStyles();
    }

    function renderBeachPolygons(geoJson) {
        if (beachesPolygonLayer) {
            map.removeLayer(beachesPolygonLayer);
            polygonLayers.length = 0;
        }

        beachesPolygonLayer = L.geoJSON(geoJson, {
            pane: 'beachPolygonsPane',
            filter: function (feature) {
                const geometryType = feature?.geometry?.type;
                return geometryType === 'Polygon' || geometryType === 'MultiPolygon';
            },
            style: function (feature) {
                const relatedBeaches = getRelatedBeachesForFeature(feature);
                const source = relatedBeaches.length > 0 ? relatedBeaches[0] : (feature.properties || {});
                const isSelectedPolygon = Boolean(selectedBeach && relatedBeaches.some(beach => beach.id === selectedBeach.id));
                return getPolygonStyle(source, isSelectedPolygon);
            },
            onEachFeature: function (feature, layer) {
                const properties = feature.properties || {};
                const popupContent = buildPolygonPopupContent(properties);
                if (popupContent) layer.bindPopup(popupContent);

                layer.on('click', function () {
                    const related = getRelatedBeachesForFeature(feature);
                    if (related.length > 0) {
                        showSameBeachInfoAsMarker(related[0]);
                    } else {
                        updateInfoPanel(properties);
                        if (document.getElementById('detail-screen').classList.contains('active')) {
                            updateDetailScreen(properties);
                        }
                    }
                });

                layer.on('mouseover', function () {
                    layer.bindTooltip(buildPolygonHoverContent(feature), {
                        sticky: true,
                        direction: 'top',
                        opacity: 0.95
                    }).openTooltip();
                });

                layer.on('mouseout', function () {
                    layer.closeTooltip();
                });

                polygonLayers.push({ feature: feature, layer: layer });
            }
        }).addTo(map);

        fitMapToAvailableData();
        refreshPolygonStyles();
    }

    function focusBeachOnMap(beach) {
        const beachId = Number(beach.id);
        const currentBeach = beaches.find(item => item.id === beachId) || beach;
        const marker = markersById.get(currentBeach.id);
        if (!marker) return;
        const latitude = Number(currentBeach.latitude);
        const longitude = Number(currentBeach.longitude);
        if (Number.isNaN(latitude) || Number.isNaN(longitude)) return;

        const focusRequestId = ++mapFocusRequestId;
        const targetLatLng = [latitude, longitude];

        selectBeach(currentBeach);
        setActiveScreen('map-screen');

        window.setTimeout(() => {
            if (focusRequestId !== mapFocusRequestId) return;
            map.stop();
            map.closePopup();
            if (!map.hasLayer(marker)) marker.addTo(map);

            map.invalidateSize(true);
            map.setView(targetLatLng, 15, { animate: false });

            window.requestAnimationFrame(() => {
                if (focusRequestId !== mapFocusRequestId) return;
                map.invalidateSize(true);
                map.setView(targetLatLng, 15, { animate: true });

                window.setTimeout(() => {
                    if (focusRequestId !== mapFocusRequestId) return;
                    openBeachPopup(marker, currentBeach);

                    window.setTimeout(() => {
                        if (focusRequestId !== mapFocusRequestId) return;
                        map.invalidateSize(true);
                        map.setView(targetLatLng, 15, { animate: false });
                    }, 80);
                }, 80);
            });
        }, 200);
    }

    function openBeachDetails(beach, sourceScreenId = null) {
        const originScreenId = sourceScreenId || getActiveScreenId();
        lastNonDetailScreen = originScreenId;
        detailReturnScreen = originScreenId === 'list-screen' ? 'map-screen' : 'list-screen';
        updateDetailBackButton();
        selectBeach(beach);
        setActiveScreen('detail-screen');
    }

    function addDetailsButtonToPopup(popup, beach) {
        const content = popup.getElement();
        if (!content || content.querySelector('.popup-detail-button')) return;

        const actions = document.createElement('div');
        actions.className = 'popup-actions';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'action-button primary popup-detail-button';
        button.dataset.action = 'show-details';
        button.dataset.id = beach.id;
        button.textContent = '\u041a\u0430\u0440\u0442\u043e\u0447\u043a\u0430 \u043f\u043b\u044f\u0436\u0430';

        actions.appendChild(button);
        const popupContent = content.querySelector('.leaflet-popup-content');
        if (popupContent) popupContent.appendChild(actions);
    }

    function openBeachPopup(marker, beach) {
        marker.setPopupContent(buildPopupContent(beach));
        marker.openPopup();

        [0, 100].forEach(delay => window.setTimeout(() => {
            addDetailsButtonToPopup(marker.getPopup(), beach);
        }, delay));
    }

    function setMapExpanded(nextState) {
        isMapExpanded = nextState;
        mapScreen.classList.toggle('is-map-expanded', isMapExpanded);
        toggleMapSizeButton.textContent = isMapExpanded ? 'Свернуть карту' : 'Развернуть карту';
        requestMapResize();
    }

    detailHeaderActions.className = 'detail-header-actions';
    detailBackButton.textContent = '\u041d\u0430\u0437\u0430\u0434';
    detailReturnButton.type = 'button';
    detailReturnButton.id = 'detail-return-button';
    detailReturnButton.className = 'back-button';
    detailBackButton.parentNode.insertBefore(detailHeaderActions, detailBackButton);
    detailHeaderActions.appendChild(detailBackButton);
    detailHeaderActions.appendChild(detailReturnButton);
    updateDetailBackButton();

    detailTitleRow.className = 'detail-title-row';
    detailGeoWrap.className = 'detail-geo-wrap';
    detailGeoButton.type = 'button';
    detailGeoButton.className = 'detail-geo-button';
    detailGeoButton.title = '\u043d\u0430 \u043a\u0430\u0440\u0442\u0435';
    detailGeoButton.innerHTML = '<img src="/map%20generated%20image.png" alt="">';
    detailCoordinates.type = 'button';
    detailCoordinates.className = 'detail-coordinates';
    detailCoordinates.title = '\u0441\u043a\u043e\u043f\u0438\u0440\u043e\u0432\u0430\u0442\u044c \u043a\u043e\u043e\u0440\u0434\u0438\u043d\u0430\u0442\u044b';
    detailName.parentNode.insertBefore(detailTitleRow, detailName);
    detailTitleRow.appendChild(detailName);
    detailGeoWrap.appendChild(detailGeoButton);
    detailGeoWrap.appendChild(detailCoordinates);
    detailTitleRow.appendChild(detailGeoWrap);

    navButtons.forEach(button => {
        button.addEventListener('click', function () {
            setActiveScreen(button.dataset.screenTarget);
        });
    });

    searchInput.addEventListener('input', function () {
        syncSearchStateFromInput();
        renderBeachesList();
    });

    filterChips.forEach(chip => {
        chip.addEventListener('click', function () {
            activeCategory = chip.dataset.category;
            filterChips.forEach(button => {
                button.classList.toggle('active', button === chip);
            });
            renderBeachesList();
        });
    });

    beachesList.addEventListener('click', function (event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const beachId = Number(button.dataset.id);
        const beach = beaches.find(item => item.id === beachId);
        if (!beach) return;

        if (button.dataset.action === 'show-on-map') focusBeachOnMap(beach);
        if (button.dataset.action === 'show-details') openBeachDetails(beach, 'list-screen');
    });

    mapElement.addEventListener('click', function (event) {
        const button = event.target.closest('[data-action="show-details"]');
        if (!button) return;

        const beachId = Number(button.dataset.id);
        const beach = beaches.find(item => item.id === beachId);
        if (!beach) return;

        openBeachDetails(beach, 'map-screen');
    });

    detailBackButton.addEventListener('click', function () {
        setActiveScreen(lastNonDetailScreen);
    });

    detailReturnButton.addEventListener('click', function () {
        setActiveScreen(detailReturnScreen);
    });

    function focusCurrentDetailBeachOnMap() {
        const beachId = Number(detailMapButton.dataset.id);
        const beach = beaches.find(item => item.id === beachId);
        if (!beach) return;
        focusBeachOnMap(beach);
    }

    detailMapButton.addEventListener('click', function () {
        focusCurrentDetailBeachOnMap();
    });

    detailGeoButton.addEventListener('click', function () {
        focusCurrentDetailBeachOnMap();
    });

    detailCoordinates.addEventListener('click', function () {
        const coordinates = detailCoordinates.dataset.coordinates;
        if (!coordinates) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(coordinates);
            return;
        }

        const tempInput = document.createElement('input');
        tempInput.value = coordinates;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        tempInput.remove();
    });

    toggleMapSizeButton.addEventListener('click', function () {
        setMapExpanded(!isMapExpanded);
    });

    fitMapButton.addEventListener('click', function () {
        fitMapToAvailableData();
    });

    mapScreen.addEventListener('transitionend', function (event) {
        if (event.target === mapElement || event.target === mapScreen) {
            map.invalidateSize();
        }
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    renderLoadingState();

    fetch('/api/beaches')
        .then(response => response.json())
        .then(data => {
            beaches.push(...data);
            if (beachesPolygonLayer) refreshPolygonStyles();
            renderMapMarkers();
            renderBeachesList();

            if (beaches.length > 0) {
                selectBeach(beaches[0]);
            } else {
                updateInfoPanel({});
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки пляжей:', error);
            beachesList.innerHTML = '<div class="empty-state">Не удалось загрузить данные пляжей.</div>';
            infoName.textContent = 'Нет данных';
        });

    fetch('/api/beach-polygons')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status + ' ' + response.statusText);
            return response.json();
        })
        .then(data => {
            if (!data || (data.type !== 'FeatureCollection' && data.type !== 'Feature')) {
                throw new Error('Ожидался GeoJSON FeatureCollection или Feature.');
            }
            renderBeachPolygons(data);
        })
        .catch(error => {
            console.error('Ошибка загрузки GeoJSON полигонов пляжей:', error);
        });

    function getActiveScrollableElement() {
        const activeScreen = document.querySelector('.screen.active');
        if (activeScreen && activeScreen.scrollHeight > activeScreen.clientHeight) {
            return activeScreen;
        }
        return window;
    }

    function updateScrollTopButtonVisibility() {
        const scrollable = getActiveScrollableElement();
        const currentScroll = scrollable === window ? window.scrollY : scrollable.scrollTop;
        scrollTopButton.classList.toggle('visible', currentScroll > 120);
    }

    function updateStickyFilterOffset() {
        const topbarHeight = topbar ? topbar.getBoundingClientRect().height : 68;
        document.documentElement.style.setProperty('--filter-sticky-top', `${Math.ceil(topbarHeight + 10)}px`);
    }

    screens.forEach(screen => {
        screen.addEventListener('scroll', updateScrollTopButtonVisibility);
    });
    window.addEventListener('scroll', updateScrollTopButtonVisibility);
    window.addEventListener('resize', updateStickyFilterOffset);

    scrollTopButton.addEventListener('click', function () {
        const scrollable = getActiveScrollableElement();
        if (scrollable === window) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        scrollable.scrollTo({ top: 0, behavior: 'smooth' });
    });

    clearSearchButton.addEventListener('click', function () {
        searchInput.value = '';
        searchQuery = '';
        renderBeachesList();
        searchInput.focus();
    });

    updateStickyFilterOffset();
    updateScrollTopButtonVisibility();
</script>
</body>
</html>
