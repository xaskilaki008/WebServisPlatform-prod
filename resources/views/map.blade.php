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
            flex: 1 !important;
            border-radius: 40px !important;
            border: none !important;
            background: transparent !important;
            padding: 8px 24px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }

        .nav-button.active {
            background-color: #ffffff !important;
            color: #0b669c !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important;
        }
        .nav-button:hover:not(.active) {
            color: var(--accent);
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
        .detail-card,
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .panel,
        .detail-card,
        .filter-panel {
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            overflow: visible;
        }

        .legend-text {
            flex: 0.7; 
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* По умолчанию (на ПК) прячем мобильную картинку */
        .mobile-legend-image {
            display: none;
        }

        /* Настройки для трех картинок на ПК */
        .desktop-legend-images {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .desktop-legend-images img {
            height: 74px;
            width: auto;
            object-fit: contain;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .desktop-legend-images img:hover {
            transform: translateY(-3px) scale(1.15); 
            box-shadow: 0 8px 16px rgba(11, 102, 156, 0.15);

            position: relative; 
            z-index: 10;
        }
        .legend-panel h3 {
            margin: 0;
            font-size: 48px; /* Делаем заголовок крупнее */
            font-weight: 700;
            line-height: 1.2;
        }
        .legend-meta {
            margin: 0;
            font-size: 54.5px; /* Увеличиваем текст (был 13px) */
            color: #0b669c; /* Оставляем твой фирменный цвет */
            line-height: 1.45; /* Даем тексту "подышать" */
        }

        .legend-image {
            flex: 1; /* Картинка берет коэффициент 1 */
            width: 100%;
            max-width: 68%; /* Защита, чтобы картинка не выросла слишком сильно */
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
            /* Вычитаем высоту topbar (примерно 80-100px), чтобы не было вертикальной прокрутки */
            height: calc(100vh - 100px) !important; 
            min-height: 600px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            z-index: 1;
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
        /* ОБЩИЕ СТИЛИ ДЛЯ ПЕРЕКЛЮЧАТЕЛЯ (Мобильный вид по умолчанию) */
        .topbar-nav {
            display: flex !important;
            background-color: #eef2f6 !important;
            border: 2px solid #cbd5e1 !important; 
            border-radius: 50px !important;
            padding: 4px !important;
            gap: 4px !important;
            
            /* Делаем резиновую ширину для мобильных */
            width: 100% !important; 
            max-width: 100% !important;
            margin: 0 auto !important;
            box-sizing: border-box !important;
        }

        .nav-button {
            /* flex: 1 заставляет обе кнопки делиться пространством ровно 50/50 */
            flex: 1 !important; 
            width: 100% !important; /* Растягиваем саму кнопку, чтобы кликалась вся область */
            
            border-radius: 40px !important;
            border: none !important;
            background: transparent !important;
            padding: 12px 10px !important; /* Увеличили padding для удобного тапа пальцем */
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center !important;
            white-space: nowrap !important; /* Запрещаем тексту переноситься на вторую строку */
        }

        .nav-button.active {
            background-color: #ffffff !important;
            color: #0b669c !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important;
        }
        /* --- Кнопка скролла вниз (мобильная) --- */
        .scroll-down-mobile {
            position: fixed;
            bottom: 80px; /* Отступ снизу (чтобы не перекрывала твою кнопку "Вверх" или другие элементы) */
            left: 50%; /* Ставим по центру экрана */
            transform: translateX(-50%); /* Точное центрирование */
            
            /* Стили как у кнопки "Вверх" */
            background: #ffffff;
            color: #0b669c;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(11, 102, 156, 0.15);
            z-index: 1000;
            cursor: pointer;
            
            /* Плавное появление/исчезновение */
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        /* Класс для скрытия кнопки */
        .scroll-down-mobile.hidden {
            opacity: 0;
            visibility: hidden;
        }
        .detail-image-container {
            position: relative;
            margin-bottom: 16px;
            width: 100%;
            /* Центрируем слайдер в карточке */
            display: flex;
            justify-content: center;
        }
        .detail-image-container, .popup-image-container {
            position: relative; /* Чтобы стрелки позиционировались ровно по краям картинки */
        }

        /* Дизайн кнопок Влево/Вправо */
        .photo-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 50%;
            font-size: 16px;
            z-index: 10;
        }

        .photo-nav-btn:hover { background: rgba(0, 0, 0, 0.8); }
        .photo-nav-btn.left { left: 10px; }
        .photo-nav-btn.right { right: 10px; }

        /* Особый отступ для большого попапа */
        .popup-nav.left { left: -50px; }
        .popup-nav.right { right: -50px; }
        @media (max-width: 819px) {
            .popup-nav.left { left: 10px; }
            .popup-nav.right { right: 10px; }
        }

        /* Счетчик "1 / 3" */
        .photo-counter {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            z-index: 10;
            pointer-events: none;
        }

        .hidden { display: none !important; }
        .beach-thumbnail {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 16px; /* Закругленные углы */
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12); /* Мягкая тень */
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .beach-thumbnail:hover {
            transform: scale(1.02); /* Легкое увеличение при наведении */
        }
        /* --- Компактная кликабельная карточка --- */
        .list-card.compact {
            background-color: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px; /* Меньше скругление */
            padding: 8px 12px; /* Убрали лишний воздух */
            
            position: relative;
            display: flex; /* Выстраиваем элементы в горизонтальную линию */
            align-items: center; /* Центрируем по вертикали */
            gap: 12px;
            cursor: pointer;
            transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }

        .list-card.compact:hover {
            transform: translateY(-2px);
            border-color: #93c5fd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .list-id-compact {
            font-size: 16px;
            font-weight: 700;
            color: #94a3b8;
            min-width: 24px;
        }

        .list-card-content {
            flex-grow: 1; /* Текст занимает всё свободное место посередине */
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .compact-title { margin: 0; font-size: 14px; font-weight: 600; }

        .compact-meta {
            display: flex; gap: 8px; align-items: center;
            font-size: 11px; color: #64748b;
        }

        .compact-meta .category-badge {
            padding: 2px 6px; font-size: 10px; /* Уменьшили бейджик */
        }

        .list-actions-compact { margin: 0; }

        /* Маленькая кнопка "Карта" */
        .list-card.compact .action-button.small {
            position: relative;
            z-index: 2;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
        }
        /* --- Карусель картинок (Swipe-эффект) --- */
        .slider-viewport {
            overflow: hidden;
            width: 100%;
            /* === ОГРАНИЧЕНИЕ ШИРИНЫ НА ПК === */
            max-width: 400px; 
            border-radius: 12px;
            background: #f1f5f9;
            cursor: zoom-in; /* Показываем, что на картинку можно нажать */
        }

        .slider-track {
            display: flex;
            width: 100%;
            /* Вот она, та самая плавная анимация скольжения */
            transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1); 
            will-change: transform;
        }

        .slider-slide {
            flex: 0 0 100%; /* На ПК лучше делать 100%, если ширина всего 400px */
            padding: 0;
            box-sizing: border-box;
            transition: opacity 0.4s ease;
        }

        .slider-slide.active {
            opacity: 1;
            transform: scale(1); /* Центральная картинка в полном фокусе */
        }

        .slider-slide img {
            width: 100%;
            height: 300px; /* Можно немного увеличить высоту для 400px ширины */
            object-fit: contain;
            background-color: #d8d8d8;
            display: block;
        }
        .image-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9); /* Темный фон */
            backdrop-filter: blur(8px); /* Размытие заднего плана */
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .popup-wrapper {
            position: relative;
            width: 90%;
            max-width: 900px;
            height: 80vh;
            display: flex;
            flex-direction: column;
        }

        .popup-content {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 20px;
        }

        .popup-image-container {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        #popup-large-photo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
        }

        .close-popup-btn {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }

        .popup-nav-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            transition: 0.3s;
        }

        .popup-nav-btn:hover { background: rgba(255, 255, 255, 0.3); }

        .hidden { display: none !important; }
        .gallery-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            width: 100%;
        }

        /* Линия миниатюр */
        .thumbnails-line {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 2px;
            scrollbar-width: thin; /* Тонкий скролл для Firefox */
        }

        .thumbnails-line::-webkit-scrollbar { height: 4px; }
        .thumbnails-line::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .thumb-item {
            width: 70px;
            height: 70px;
            flex: 0 0 70px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            opacity: 0.7;
        }

        .thumb-item:hover { opacity: 1; }
        .thumb-item.active {
            border-color: #3b82f6; /* Синяя рамка вокруг активного фото */
            opacity: 1;
            transform: scale(1.05);
        }

        /* Блок главного фото */
        .main-photo-box {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .main-photo-wrapper {
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        #gallery-main-img {
            width: 100%;
            height: 280px;
            object-fit: contain;
            background-color: #000; /* Черный фон для фото */
            border-radius: 12px;
            cursor: zoom-in;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .photo-number-label {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
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
            .topbar-nav {
                max-width: 400px !important; 
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
            .scroll-down-mobile {
                display: none !important;
            }
            #map {
                height: 500px !important; 
                

                
                min-height: 400px !important;
                border-radius: var(--radius-lg);
            }

           #map-screen.is-map-expanded #map {
                height: 650px !important;
            }
        }

        @media (min-width: 1120px) {
            .map-layout {
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
        @media (min-width: 1200px) {
            .main-container {
                max-width: 1800px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            #map {
                /* Вместо 100vh ставим фиксированную приятную высоту */
                height: 550px !important; 
                min-height: 500px !important;
                /* Ограничиваем, чтобы карта не «улетала» вниз на огромных экранах */
                max-height: 65vh !important; 
            }

            /* Когда карта развернута кнопкой «Развернуть» */
            #map-screen.is-map-expanded #map {
                height: 750px !important;
                max-height: 85vh !important;
            }
        }
        @media (max-width: 819px) {
            .topbar-inner {
                flex-direction: column;
                align-items: stretch;
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

            /* --- Стили для легенды теперь лежат прямо здесь, без лишнего @media --- */
            .legend-panel {
                flex-direction: column;
                align-items: flex-start;
            }

            /* На телефоне ПРЯЧЕМ три отдельные картинки */
            .desktop-legend-images {
                display: none;
            }

            /* На телефоне ПОКАЗЫВАЕМ старую длинную картинку */
            .mobile-legend-image {
                display: block; 
                width: 100%;
                max-width: 100%;
                margin-top: 8px;
                object-fit: contain;
                border-radius: var(--radius-md);
                background: var(--surface-soft);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
                animation: none !important;
            }
        }
        /* --- Скрытая кнопка --- */
        .ghost-btn {
            position: fixed;
            bottom: 20px;
            right: 20px; /* Размещаем в правом нижнем углу */
            background: transparent;
            border: none;
            color: #94a3b8; /* Бледный цвет, сливающийся с картой/фоном */
            opacity: 0.3; /* Почти невидимая */
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .ghost-btn:hover {
            opacity: 1;
            color: #0b669c; /* При наведении становится фирменной синей */
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* --- Модальное окно --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6); /* Темная подложка */
            backdrop-filter: blur(4px); /* Размытие фона */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.hidden {
            opacity: 0;
            pointer-events: none; /* Чтобы нельзя было кликнуть, когда скрыто */
        }

        .modal-content {
            background: #ffffff;
            padding: 32px;
            border-radius: 16px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            position: relative;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .modal-overlay.hidden .modal-content {
            transform: translateY(20px); /* Легкая анимация выезда снизу */
        }

        /* Элементы внутри окна */
        .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
        }

        .modal-content h2 {
            margin: 0 0 8px 0;
            color: #0f172a;
            font-size: 20px;
        }

        .modal-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 24px;
        }

        .input-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .input-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .input-group input:focus {
            border-color: #0b669c;
            box-shadow: 0 0 0 3px rgba(11, 102, 156, 0.1);
        }

        .primary-btn {
            width: 100%;
            padding: 12px;
            background: #0b669c;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
        }

        .primary-btn:hover {
            background: #084e79;
        }
        /* --- Миниатюра в панели --- */
        .beach-thumbnail {
            width: 100%;
            max-width: 300px; /* Ограничиваем, чтобы не была огромной */
            height: auto;
            border-radius: 12px; /* Закругленные углы */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); /* Тень */
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 16px;
        }

        /* Эффект при наведении на миниатюру */
        .beach-thumbnail:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
        }

        /* --- Попап (Темный фон) --- */
        .image-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.85); /* Темно-синий полупрозрачный фон */
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .image-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }

        /* --- Контейнер картинки --- */
        .popup-image-container {
            position: relative;
            /* Твое условие: ширина на 200px меньше экрана */
            width: calc(100vw - 200px); 
            max-width: 1200px; /* Защита для огромных мониторов */
            display: flex;
            justify-content: center;
        }

        /* Сама большая картинка */
        .popup-large-photo {
            width: 100%;
            height: auto;
            /* Автовычисление высоты, но не больше 90% от высоты экрана, чтобы влезала */
            max-height: 90vh; 
            object-fit: contain; /* Картинка не будет обрезаться */
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        /* Кнопка закрытия (Крестик) */
        .close-popup-btn {
            position: absolute;
            top: -40px;
            right: -40px;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 40px;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s ease;
        }

        .close-popup-btn:hover {
            color: #cbd5e1;
        }

        /* Защита для смартфонов (экраны меньше 820px) */
        @media (max-width: 819px) {
            .popup-image-container {
                width: calc(100vw - 32px); /* На телефоне картинка занимает почти весь экран */
            }
            .close-popup-btn {
                top: -45px;
                right: 0px; /* Сдвигаем крестик внутрь, чтобы не улетел за экран */
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
                        <div class="legend-text">
                            <h3>Легенда статусов</h3>
                            <p class="legend-meta">Флажок и полигон связаны единым цветом категории безопасности.</p>
                        </div>
                        
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
                <div class="gallery-container">
                    <div id="gallery-thumbnails" class="thumbnails-line"></div>

                    <div id="gallery-main-display" class="main-photo-box hidden">
                        <div class="main-photo-wrapper">
                            <img id="gallery-main-img" src="" alt="Фото пляжа" onclick="openImagePopup(currentPhotoIndex)">
                            <div id="gallery-photo-number" class="photo-number-label"></div>
                        </div>
                    </div>
                </div>
               <h2 id="detail-name">Пляж не выбран</h2>

                <div class="detail-fields">
                    <div class="detail-field"><strong>Номер:</strong> <span id="detail-number">-</span></div>
                    <div class="detail-field"><strong>Уровень волнения:</strong> <span id="detail-wave-level">-</span></div>
                    <div class="detail-field"><strong>Описание волнения:</strong> <span id="detail-wave-text">Нет данных</span></div>
                    <div class="detail-field"><strong>Категория:</strong> <span id="detail-category">-</span></div>
                    
                    <div class="detail-field"><strong>Высота волны:</strong> <span id="detail-wave-height">-</span></div>
                    <div class="detail-field"><strong>Период волны:</strong> <span id="detail-wave-period">-</span></div>
                    <div class="detail-field"><strong>Направление:</strong> <span id="detail-wave-direction">-</span></div>
                </div>
            </article>
        </section>
    </main>
</div>
<!-- Скрытая кнопка для вызова окна авторизации -->
<button id="secret-login-btn" class="ghost-btn" aria-label="Вход для сотрудников">
    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
    </svg>
</button>

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
    </div>
</div>
<button id="scroll-down-btn" class="scroll-down-mobile hidden" aria-label="Прокрутить вниз">↓</button>
<button id="scroll-top-button" class="scroll-top-button" type="button">↑</button>
<!-- Попап для просмотра большой картинки -->
<div id="image-popup" class="image-overlay hidden">
    <div class="popup-wrapper">
        <button id="close-image-popup" class="close-popup-btn">&times;</button>
        
        <div class="popup-content">
            <button id="popup-prev" class="popup-nav-btn left" onclick="changePhoto(-1, event)">&#10094;</button>
            
            <div class="popup-image-container">
                <img src="" id="popup-large-photo" class="popup-large-photo" alt="Пляж">
                <div id="popup-counter" class="photo-counter"></div>
            </div>
            
            <button id="popup-next" class="popup-nav-btn right" onclick="changePhoto(1, event)">&#10095;</button>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
<script>
    let currentPhotos = [];
    let currentPhotoIndex = 0;
    const topbar = document.querySelector('.topbar');
    const mapScreen = document.getElementById('map-screen');
    const mapElement = document.getElementById('map');
    const map = L.map(mapElement).setView([44.61, 33.52], 11);
    map.zoomControl.setPosition('topright');
    map.createPane('beachPolygonsPane');
    map.getPane('beachPolygonsPane').style.zIndex = '350';
    map.getPane('beachPolygonsPane').style.pointerEvents = 'auto';
    map.attributionControl.setPrefix(false);
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
    const sidebarPhoto = document.getElementById('sidebar-beach-photo');

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
        // 1. Заполняем то, что уже есть (мгновенно)
        detailName.textContent = beach.name || 'Без названия';
        detailNumber.textContent = beach.number ?? '-';
        detailWaveLevel.textContent = beach.wave_level ?? '-';
        detailWaveText.textContent = getWaveLevelText(beach.wave_level);
        detailCategory.textContent = getBeachCategoryLabel(beach);
        detailMapButton.dataset.id = beach.id ?? '';

        const hasCoordinates = beach.latitude !== undefined && beach.latitude !== null && beach.longitude !== undefined && beach.longitude !== null;
        detailCoordinates.textContent = hasCoordinates ? `${beach.latitude}, ${beach.longitude}` : '-';
        detailCoordinates.dataset.coordinates = hasCoordinates ? `${beach.latitude}, ${beach.longitude}` : '';

        // --- 2. НОВЫЙ КОД: СТУЧИМСЯ В БАЗУ ЗА ВОЛНАМИ ---
        if (beach.id) {
            fetch(`/api/beach-info/${beach.id}`)
                .then(response => response.json())
                .then(data => {
                    // Обрати внимание, берем latest_forecast из ответа
                    const forecast = data.latest_forecast;

                    if (forecast) {
                        // Если данные в базе есть, вставляем их
                        document.getElementById('detail-wave-height').innerText = forecast.wave_height + ' м';
                        document.getElementById('detail-wave-period').innerText = forecast.wave_period + ' сек';
                        document.getElementById('detail-wave-direction').innerText = forecast.wave_direction + '°';
                        detailWaveText.innerText = 'Данные DWD обновлены';
                    } else {
                        // Если парсер еще не собрал данные
                        document.getElementById('detail-wave-height').innerText = 'нет данных';
                        document.getElementById('detail-wave-period').innerText = 'нет данных';
                        document.getElementById('detail-wave-direction').innerText = 'нет данных';
                        detailWaveText.innerText = 'Прогноз ожидается';
                    }
                })
                .catch(err => {
                    console.error('Ошибка связи с БД при загрузке волн:', err);
                    detailWaveText.innerText = 'Ошибка загрузки';
                });
        }

        // --- 3. СТАРЫЙ КОД: ОТОБРАЖЕНИЯ ГАЛЕРЕИ ---
        // Очищаем галерею перед загрузкой нового пляжа
        currentPhotos = [];
        currentPhotoIndex = 0;
        renderGallery();

        // Делаем запрос к серверу за массивом фотографий
        if (beach.id) {
            fetch(`/api/beach-photo/${beach.id}`)
                .then(response => response.json())
                .then(data => {
                    currentPhotos = data.photo_urls || [];
                    renderGallery(); // Рисуем новые фото
                })
                .catch(() => {
                    currentPhotos = [];
                    renderGallery();
                });
        }
    }
    // Функция обновления интерфейса галереи
    function renderGallery() {
        const thumbContainer = document.getElementById('gallery-thumbnails');
        const mainDisplay = document.getElementById('gallery-main-display');

        if (currentPhotos.length === 0) {
            thumbContainer.innerHTML = '';
            mainDisplay.classList.add('hidden');
            return;
        }

        mainDisplay.classList.remove('hidden');

        // Рисуем ленту миниатюр
        thumbContainer.innerHTML = currentPhotos.map((url, index) => `
        <img src="${url}" 
             class="thumb-item ${index === 0 ? 'active' : ''}" 
             onclick="setMainPhoto(${index})" 
             data-index="${index}">
    `).join('');

        // По умолчанию показываем первое фото
        setMainPhoto(0);
    }

    function setMainPhoto(index) {
        currentPhotoIndex = index;
        const mainImg = document.getElementById('gallery-main-img');
        const numberLabel = document.getElementById('gallery-photo-number');
        const thumbs = document.querySelectorAll('.thumb-item');

        // Обновляем картинку и номер
        mainImg.src = currentPhotos[index];
        numberLabel.textContent = `Фотография ${index + 1} из ${currentPhotos.length}`;

        // Подсвечиваем активную миниатюру
        thumbs.forEach(t => t.classList.remove('active'));
        const activeThumb = document.querySelector(`.thumb-item[data-index="${index}"]`);
        if (activeThumb) {
            activeThumb.classList.add('active');
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        // Если попап открыт (Instagram-style), синхронизируем и его
        if (typeof syncPopup === 'function') syncPopup();
    }
        function openImagePopup(index) {
        currentPhotoIndex = index;
        document.getElementById('image-popup').classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Запрещаем скролл страницы
        syncPopup();
    }
    function closeImagePopup() {
        document.getElementById('image-popup').classList.add('hidden');
        document.body.style.overflow = ''; // Возвращаем скролл
    }

    function syncPopup() {
        const popupPhoto = document.getElementById('popup-large-photo');
        const popupCounter = document.getElementById('popup-counter');
        const popup = document.getElementById('image-popup');

        if (!popup.classList.contains('hidden') && currentPhotos[currentPhotoIndex]) {
            popupPhoto.src = currentPhotos[currentPhotoIndex];
            popupCounter.textContent = `${currentPhotoIndex + 1} / ${currentPhotos.length}`;
        }
    }
    // Функция для клика по стрелкам
    // Единственная правильная функция для клика по стрелкам
    function changePhoto(step, event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!currentPhotos || currentPhotos.length <= 1) return;

        // Вычисляем новый индекс
        currentPhotoIndex += step;

        // Зацикливаем прокрутку
        if (currentPhotoIndex < 0) {
            currentPhotoIndex = currentPhotos.length - 1;
        } else if (currentPhotoIndex >= currentPhotos.length) {
            currentPhotoIndex = 0;
        }

        // Вызываем новую функцию обновления главного фото
        if (typeof setMainPhoto === 'function') {
            setMainPhoto(currentPhotoIndex);
        }
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

        // Очищаем и заново собираем список
        beachesList.innerHTML = filteredBeaches.map(beach => {
            const selectedClass = selectedBeach && selectedBeach.id === beach.id ? ' selected' : '';
            return `
                <article class="list-card compact${selectedClass}" data-action="show-details" data-id="${beach.id}">
                    <div class="list-id-compact">${beach.number ?? '-'}</div>
                    <div class="list-card-content">
                        <h3 class="compact-title">${beach.name || 'Без названия'}</h3>
                        <div class="compact-meta">
                            <span class="category-badge ${getCategoryBadgeClass(beach)}">${getBeachCategoryLabel(beach)}</span>
                            <span class="wave-info">Волны: ${beach.wave_level ?? '-'}</span>
                        </div>
                    </div>
                    <div class="list-actions-compact">
                        <!-- Кнопка стала меньше и аккуратнее -->
                        <button type="button" class="action-button primary small" data-action="show-on-map" data-id="${beach.id}">Карта</button>
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
        // updateInfoPanel(beach);
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
                focusBeachOnMap(beach);
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
                        // Новый вызов: теперь будет центрирование и зум
                        focusBeachOnMap(related[0]); 
                    } else {
                        // updateInfoPanel(properties);
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
                // updateInfoPanel({});
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки пляжей:', error);
            beachesList.innerHTML = '<div class="empty-state">Не удалось загрузить данные пляжей.</div>';
            // infoName.textContent = 'Нет данных';
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
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Объявляем все переменные строго ОДИН раз в самом начале
        const loginBtn = document.getElementById('secret-login-btn');
        const modal = document.getElementById('login-modal');
        const closeBtn = document.getElementById('close-modal-btn');
        
        const scrollDownBtn = document.getElementById('scroll-down-btn');
        const legendPanel = document.querySelector('.legend-panel');
        const imageOverlay = document.getElementById('image-popup');
        const popupLargePhoto = document.getElementById('popup-large-photo');
        const closePopupBtn = document.getElementById('close-image-popup');
        // 2. Логика модального окна входа (с проверкой, что элементы существуют)
        if (loginBtn && modal && closeBtn) {
            // Открыть модалку
            loginBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

            // Закрыть по крестику
            closeBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });

            // Закрыть по клику вне белого окна (на темный фон)
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
            document.getElementById('photo-prev')?.addEventListener('click', (e) => changePhoto(-1, e));
            document.getElementById('photo-next')?.addEventListener('click', (e) => changePhoto(1, e));
            document.getElementById('close-image-popup')?.addEventListener('click', closeImagePopup);
            document.getElementById('popup-prev')?.addEventListener('click', (e) => changePhoto(-1, e));
            document.getElementById('popup-next')?.addEventListener('click', (e) => changePhoto(1, e));

            // Закрытие по клику на фон
            document.getElementById('image-popup')?.addEventListener('click', (e) => {
                if (e.target.id === 'image-popup') closeImagePopup();
            });
        }

        // 3. Логика кнопки скролла вниз
        if (scrollDownBtn) {
            // Логика появления/исчезновения кнопки
            window.addEventListener('scroll', () => {
                // Если мы прокрутили вниз больше чем на 50 пикселей - прячем кнопку
                if (window.scrollY > 50) {
                    scrollDownBtn.classList.add('hidden');
                } else {
                    // Если мы в самом верху - показываем кнопку
                    scrollDownBtn.classList.remove('hidden');
                }
            });

            // Проверяем состояние сразу при загрузке страницы
            if (window.scrollY > 50) {
                scrollDownBtn.classList.add('hidden');
            } else {
                scrollDownBtn.classList.remove('hidden');
            }

            // Логика прокрутки при клике
            scrollDownBtn.addEventListener('click', () => {
                if (legendPanel) {
                    // Плавная прокрутка к блоку с флажками
                    legendPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    // Запасной вариант
                    window.scrollBy({ top: window.innerHeight * 0.7, behavior: 'smooth' });
                }
            });
        }
        if (imageOverlay && popupLargePhoto && closePopupBtn) {
        
            // 1. Открытие картинки при клике на ЛЮБУЮ миниатюру с классом beach-thumbnail
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('beach-thumbnail')) {
                    // Берем ссылку (src) из маленькой картинки и вставляем в большую
                    popupLargePhoto.src = e.target.src;
                    imageOverlay.classList.remove('hidden');
                }
            });

            // 2. Закрытие по крестику
            closePopupBtn.addEventListener('click', () => {
                imageOverlay.classList.add('hidden');
                setTimeout(() => popupLargePhoto.src = '', 300); // Очищаем src после анимации
            });

            // 3. Закрытие при клике мимо картинки (на темный фон)
            imageOverlay.addEventListener('click', (e) => {
                if (e.target === imageOverlay) {
                    imageOverlay.classList.add('hidden');
                    setTimeout(() => popupLargePhoto.src = '', 300);
                }
            });
        }
        // --- ЛОГИКА "УМНОГО ТУМБЛЕРА" ДЛЯ КНОПОК НАВИГАЦИИ ---
        const navButtons = document.querySelectorAll('.nav-button');
        const topbarNav = document.querySelector('.topbar-nav');

        if (navButtons.length === 2 && topbarNav) {
            // 1. Обработка клика по самим кнопкам
            navButtons.forEach(button => {
                // Добавляем true в конце, чтобы перехватить клик ДО старых скриптов
                button.addEventListener('click', function(e) {
                    
                    // Защита от бесконечного цикла: реагируем только на клики мышки/пальца
                    if (!e.isTrusted) return; 

                    // Если кнопка УЖЕ активна
                    if (this.classList.contains('active')) {
                        e.preventDefault();
                        e.stopPropagation(); // Блокируем старый скрипт
                        
                        const otherButton = Array.from(navButtons).find(btn => btn !== this);
                        if (otherButton) {
                            otherButton.click(); // Робот нажимает на вторую кнопку
                        }
                    }
                }, true); // <-- Вот эта магия (Фаза перехвата)
            });

            // 2. Если пользователь промазал и кликнул в серый фон
            topbarNav.addEventListener('click', function(e) {
                if (!e.isTrusted) return; // Тоже защищаем от цикла
                
                if (e.target === this) { 
                    const inactiveButton = Array.from(navButtons).find(btn => !btn.classList.contains('active'));
                    if (inactiveButton) {
                        inactiveButton.click();
                    }
                }
            });
        }
    });
    updateStickyFilterOffset();
    updateScrollTopButtonVisibility();
</script>
</body>
</html>
