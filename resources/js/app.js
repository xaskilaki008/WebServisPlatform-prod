import './bootstrap';
// Пример функции, которая срабатывает при клике на пляж на карте
function onBeachClick(beachId) {
    // 1. Сначала запрашиваем данные из БД
    fetch(`/api/beach-info/${beachId}`)
        .then(response => response.json())
        .then(beach => {
            const forecast = beach.latest_forecast;

            // 2. Обновляем текстовые поля в detail-card
            // Предположим, у тебя в HTML есть элементы с такими классами:
            document.querySelector('.beach-name').innerText = beach.name;

            if (forecast) {
                document.querySelector('.wave-height').innerText = `${forecast.wave_height} м`;
                document.querySelector('.wave-period').innerText = `${forecast.wave_period} сек`;
                document.querySelector('.wave-direction').innerText = `${forecast.wave_direction}°`;
            } else {
                // Если парсер еще не запускался для этого пляжа
                document.querySelectorAll('.wave-data').forEach(el => el.innerText = '--');
            }
        });
    // 3. Запрашиваем фото (твой существующий маршрут)
    fetch(`/api/beach-photo/${beachId}`)
        .then(response => response.json())
        .then(data => {
            if (data.photo_url) {
                document.querySelector('.beach-image').src = data.photo_url;
            }
        });
}

//Тут всё из map.blade.php:
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
    // --- ЭКСПОРТ ФУНКЦИЙ ДЛЯ HTML ---
    window.setMainPhoto = setMainPhoto;
    window.changePhoto = changePhoto;
    window.closeImagePopup = closeImagePopup;
    window.openImagePopup = openImagePopup;
    let mapFocusRequestId = 0;
    function getWaveLevelText(level) {
        const descriptions = [
            'Штиль (зеркальная гладь)',
            'Тихо (легкая рябь)',
            'Слабое волнение',
            'Легкое волнение',
            'Умеренное волнение',
            'Бурное море',
            'Очень бурное море',
            'Сильное волнение',
            'Очень сильное (шторм)',
            'Ураган'
        ];
        const lvl = Number(level);
        if (Number.isNaN(lvl) || lvl < 0) return 'Нет данных';
        if (lvl >= descriptions.length) return 'Экстремальный шторм';
        return descriptions[lvl];
    }
    function getWaveDirectionText(degrees) {
        const deg = Number(degrees);
        if (Number.isNaN(deg)) return '';

        if (deg >= 0 && deg <= 90) return 'Северо-Восток';
        if (deg > 90 && deg <= 180) return 'Юго-Восток';
        if (deg > 180 && deg <= 270) return 'Юго-Запад';
        if (deg > 270 && deg <= 360) return 'Северо-Запад';
        return '';
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
        const cleanId = Math.abs(beach.id);

        detailName.textContent = beach.name || 'Без названия';

        let rawNumber = beach.number ?? beach.num;
        detailNumber.textContent = (rawNumber !== undefined && rawNumber !== null && rawNumber !== '')
            ? Math.abs(Number(rawNumber))
            : '-';

        detailWaveLevel.textContent = beach.wave_level ?? '-';
        detailWaveText.textContent = getWaveLevelText(beach.wave_level);
        detailCategory.textContent = getBeachCategoryLabel(beach);
        // Эта строка вешает класс подсветки (зеленый/желтый/красный)
        detailCategory.className = 'category-badge ' + getCategoryBadgeClass(beach);
        detailMapButton.dataset.id = beach.id ?? '';

        const hasCoords = beach.latitude !== undefined && beach.longitude !== undefined;
        detailCoordinates.textContent = hasCoords ? `${beach.latitude}, ${beach.longitude}` : '-';
        detailCoordinates.dataset.coordinates = hasCoords ? `${beach.latitude}, ${beach.longitude}` : '';

        currentPhotos = [];
        currentPhotoIndex = 0;
        showGallerySkeleton();

        if (beach.id) {
        // Запрос данных о волнах
        fetch(`/api/beach-info/${cleanId}`)
            .then(response => response.json())
            .then(data => {
                const forecast = data.latest_forecast || data;
                if (forecast && forecast.wave_height !== undefined) {
                    document.getElementById('detail-wave-height').innerText = forecast.wave_height + ' м';
                    document.getElementById('detail-wave-period').innerText = forecast.wave_period + ' сек';

                    // Выводим направление волны с текстовой интерпретацией
                    const direction = forecast.wave_direction;
                    if (direction !== undefined && direction !== null) {
                        const textDir = getWaveDirectionText(direction);
                        // Выведет формат: "120° (Юго-Восток)"
                        document.getElementById('detail-wave-direction').innerText = `${direction}° ${textDir ? '(' + textDir + ')' : ''}`;
                    } else {
                        document.getElementById('detail-wave-direction').innerText = '-';
                    }

                    // Выводим время обновления
                    const updateTime = forecast.forecast_time || forecast.updated_at;
                    document.getElementById('detail-update-time').innerText = updateTime
                        ? new Date(updateTime).toLocaleString('ru-RU')
                        : 'нет данных';

                    // Оставляем реальное описание моря, а не текст ошибки
                    detailWaveText.innerText = getWaveLevelText(beach.wave_level);
                    
                    const opStatusText = data.operator_status === 'hazard'
                        ? '<span style="color:red;font-weight:bold;">Опасность</span>'
                        : (data.operator_status !== null && data.operator_status !== undefined ? `${data.operator_status} баллов (Бофорт)` : 'Нет данных');

                    const opStatusEl = document.getElementById('operator-status-text');
                    const opUpdateEl = document.getElementById('operator-updated-at');

                    if (opStatusEl) opStatusEl.innerHTML = opStatusText;
                    if (opUpdateEl) opUpdateEl.textContent = data.operator_updated_at || '-';
                    
                    // Делаем кнопку ссылкой на новую страницу
                    const operatorLink = document.getElementById('open-operator-link');
                    if (operatorLink) operatorLink.href = `/operator/${beach.id}`;
                    
                } else {
                    document.getElementById('detail-wave-height').innerText = 'нет данных';
                    document.getElementById('detail-wave-period').innerText = 'нет данных';
                    document.getElementById('detail-wave-direction').innerText = 'нет данных';
                    document.getElementById('detail-update-time').innerText = 'Ожидается';
                    detailWaveText.innerText = getWaveLevelText(beach.wave_level);
                }
            })
            .catch(err => {
                console.error('Ошибка загрузки волн:', err);
                document.getElementById('detail-update-time').innerText = 'Ошибка загрузки';
            });

            // Запрос фотографий
            fetch(`/api/beach-photo/${beach.id}`)
                .then(res => res.json())
                .then(data => {
                    currentPhotos = data.photo_urls || [];
                    currentPhotoIndex = 0;
                    renderGallery();
                });
                
        }
    }

    // --- ФУНКЦИИ ГАЛЕРЕИ ---

    function showGallerySkeleton() {
        const thumbContainer = document.getElementById('gallery-thumbnails');
        const mainDisplay = document.getElementById('gallery-main-display');
        const mainImg = document.getElementById('gallery-main-img');
        const numberLabel = document.getElementById('gallery-photo-number');
        const mainSkeleton = document.getElementById('skeleton-main-display');

        mainDisplay.classList.remove('hidden');
        mainImg.classList.add('hidden');
        numberLabel.classList.add('hidden');
        if (mainSkeleton) mainSkeleton.classList.remove('hidden');

        thumbContainer.innerHTML = `
        <div class="skeleton skeleton-thumb"></div>
        <div class="skeleton skeleton-thumb"></div>
        <div class="skeleton skeleton-thumb"></div>
        <div class="skeleton skeleton-thumb"></div>
    `;
    }

    function renderGallery() {
            const thumbContainer = document.getElementById('gallery-thumbnails');
            const mainDisplay = document.getElementById('gallery-main-display');
            const numberLabel = document.getElementById('gallery-photo-number');
            const mainSkeleton = document.getElementById('skeleton-main-display');

            // Кнопки навигации
            const prevBtn = document.getElementById('main-prev-btn');
            const nextBtn = document.getElementById('main-next-btn');

            if (!currentPhotos || currentPhotos.length === 0) {
                thumbContainer.innerHTML = '';
                mainDisplay.classList.add('hidden');
                if (mainSkeleton) mainSkeleton.classList.add('hidden');
                numberLabel.classList.add('hidden');
                return;
            }

            // Показываем или скрываем стрелки в зависимости от количества фото
            if (currentPhotos.length > 1) {
                if (prevBtn) prevBtn.classList.remove('hidden');
                if (nextBtn) nextBtn.classList.remove('hidden');
            } else {
                if (prevBtn) prevBtn.classList.add('hidden');
                if (nextBtn) nextBtn.classList.add('hidden');
            }

            mainDisplay.classList.remove('hidden');
            numberLabel.classList.remove('hidden');

            // ... остальной код отрисовки миниатюр ...
            thumbContainer.innerHTML = currentPhotos.map((url, index) => `
        <img src="${url}" 
                class="thumb-item ${index === 0 ? 'active' : ''}" 
                onclick="setMainPhoto(${index})" 
                data-index="${index}">
        `).join('');

            setMainPhoto(0);
    }

    function setMainPhoto(index) {
        currentPhotoIndex = index;
        const mainImg = document.getElementById('gallery-main-img');
        const numberLabel = document.getElementById('gallery-photo-number');
        const thumbs = document.querySelectorAll('.thumb-item');
        const mainSkeleton = document.getElementById('skeleton-main-display');

        if (!currentPhotos[index]) return;

        mainImg.classList.add('hidden');
        if (mainSkeleton) mainSkeleton.classList.remove('hidden');

        mainImg.onload = function () {
            mainImg.classList.remove('hidden');
            if (mainSkeleton) mainSkeleton.classList.add('hidden');
        };

        mainImg.src = currentPhotos[index];
        numberLabel.textContent = `Фотография ${index + 1} из ${currentPhotos.length}`;

        thumbs.forEach(t => t.classList.remove('active'));
        const activeThumb = document.querySelector(`.thumb-item[data-index="${index}"]`);
        if (activeThumb) {
            activeThumb.classList.add('active');
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        if (typeof syncPopup === 'function') syncPopup();
    }

    function openImagePopup(index) {
        currentPhotoIndex = index;
        document.getElementById('image-popup').classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Отрисовываем миниатюры специально для попапа
        renderPopupThumbnails();
        syncPopup();
    }
    function closeImagePopup() {
        document.getElementById('image-popup').classList.add('hidden');
        document.body.style.overflow = ''; // Возвращаем скролл
    }
    function renderPopupThumbnails() {
            const popupThumbs = document.getElementById('popup-thumbnails');
            if (!popupThumbs) return;

            popupThumbs.innerHTML = currentPhotos.map((url, index) => `
        <img src="${url}" 
             class="thumb-item ${index === currentPhotoIndex ? 'active' : ''}" 
             onclick="setMainPhoto(${index})" 
             data-popup-index="${index}">
    `).join('');
    }
    function syncPopup() {
        const popupPhoto = document.getElementById('popup-large-photo');
        const popupCounter = document.getElementById('popup-counter');
        const popup = document.getElementById('image-popup');

        if (!popup.classList.contains('hidden') && currentPhotos[currentPhotoIndex]) {
            popupPhoto.src = currentPhotos[currentPhotoIndex];
            popupCounter.textContent = `Фотография ${currentPhotoIndex + 1} из ${currentPhotos.length}`;

            // Обновляем активную миниатюру в попапе
            const allPopupThumbs = document.querySelectorAll('#popup-thumbnails .thumb-item');
            allPopupThumbs.forEach(t => t.classList.remove('active'));

            const activeThumb = document.querySelector(`#popup-thumbnails .thumb-item[data-popup-index="${currentPhotoIndex}"]`);
            if (activeThumb) {
                activeThumb.classList.add('active');
                activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        }
    }
    // Функция для клика по стрелкам
    // Единственная правильная функция для клика по стрелкам
    function changePhoto(step, event) {
            if (event) event.stopPropagation(); // Чтобы не открывался попап при клике на стрелку

            if (!currentPhotos || currentPhotos.length <= 1) return;

            // Классическая циклическая логика (1 -> 2 -> 3 -> 1)
            const total = currentPhotos.length;
            currentPhotoIndex = (currentPhotoIndex + step + total) % total;

            setMainPhoto(currentPhotoIndex);
    }
    function requestMapResize() {
        [0, 140, 260, 420].forEach(delay => {
            window.setTimeout(() => map.invalidateSize(), delay);
        });
    }

    function setActiveScreen(screenId) {
        screens.forEach(screen => {
            // Убирает класс 'active' у всех секций и вешает только на ту, чей ID мы передали
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
                    <b>Номер:</b> ${Math.abs(beach.number ?? 0) || '-'}<br>
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
        if (properties.number !== undefined && properties.number !== null && properties.number !== '') {
            lines.push('Номер: ' + Math.abs(properties.number));
        }
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
                    <div class="list-id-compact">${Math.abs(beach.number ?? 0) || '-'}</div>
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
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('beach', beach.id + '/map');
        window.history.pushState({ beachId: beach.id, mode: 'map' }, '', newUrl);
    }

    function openBeachDetails(beach, sourceScreenId = null) {
        const originScreenId = sourceScreenId || getActiveScreenId();
        lastNonDetailScreen = originScreenId;
        detailReturnScreen = originScreenId === 'list-screen' ? 'map-screen' : 'list-screen';
        updateDetailBackButton();
        selectBeach(beach);
        setActiveScreen('detail-screen');

        // Устанавливаем URL для карточки деталей
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('beach', beach.id);
        window.history.pushState({ beachId: beach.id, mode: 'detail' }, '', newUrl);
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

        // --- НОВАЯ ЛОГИКА: Очищаем URL ---
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('beach'); // Удаляем параметр
        window.history.pushState({}, '', newUrl);
    });

    // То же самое для второй кнопки возврата, если она есть
    detailReturnButton.addEventListener('click', function () {
        setActiveScreen(detailReturnScreen);

        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('beach');
        window.history.pushState({}, '', newUrl);
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
        // НАЙДИТЕ ЭТОТ БЛОК В КОНЦЕ ФАЙЛА:
        .then(data => {
            data.sort((a, b) => Math.abs(a.number || 0) - Math.abs(b.number || 0));
            beaches.push(...data);

            if (beachesPolygonLayer) refreshPolygonStyles();
            renderMapMarkers();
            renderBeachesList();

            if (beaches.length > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                const beachParam = urlParams.get('beach');

                if (beachParam) {
                    // Разделяем ID и режим (например, "32/map" -> ["32", "map"])
                    const parts = beachParam.split('/');
                    const beachId = parts[0];
                    const isMapView = parts[1] === 'map';

                    const targetBeach = beaches.find(b => String(b.id) === String(beachId));
                    if (targetBeach) {
                        if (isMapView) {
                            focusBeachOnMap(targetBeach);
                        } else {
                            openBeachDetails(targetBeach);
                        }
                    } else {
                        selectBeach(beaches[0]);
                    }
                } else {
                    selectBeach(beaches[0]);
                }
            }
        })
        
    .catch(error => {
        console.error('Ошибка загрузки пляжей:', error);
        beachesList.innerHTML = '<div class="empty-state">Не удалось загрузить данные пляжей.</div>';
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
        // --- ОТКРЫТИЕ ПОПАПА ПО КЛИКУ НА ГЛАВНУЮ КАРТИНКУ ---
        const mainGalleryImg = document.getElementById('gallery-main-img');
        if (mainGalleryImg) {
            mainGalleryImg.addEventListener('click', () => {
                // Здесь скрипт "видит" currentPhotoIndex, так как они в одном файле
                openImagePopup(currentPhotoIndex);
            });
        }
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
        // --- ЛОГИКА ДЛЯ ВРЕМЕННЫХ КНОПОК АДМИНА ---
        const forceFetchBtn = document.getElementById('force-fetch-btn');
        const toggleParsingBtn = document.getElementById('toggle-parsing-btn');

        if (forceFetchBtn) {
            forceFetchBtn.addEventListener('click', function () {
                if (confirm('ВНИМАНИЕ: Запросить свежие данные прямо сейчас? (Сбор с DWD может занять несколько секунд)')) {

                    const originalText = this.textContent;
                    this.textContent = 'Идёт загрузка...';
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none'; // Защита от двойного клика

                    fetch('/api/force-fetch', { method: 'POST' })
                        .then(res => res.json())
                        .then(data => alert(data.message || data.error))
                        .catch(err => {
                            console.error(err);
                            alert('Произошла ошибка при обращении к серверу.');
                        })
                        .finally(() => {
                            this.textContent = originalText;
                            this.style.opacity = '1';
                            this.style.pointerEvents = 'auto';
                        });
                }
            });
        }

        if (toggleParsingBtn) {
            toggleParsingBtn.addEventListener('click', function () {
                if (confirm('ВНИМАНИЕ: Изменить режим работы ежечасного парсера?')) {
                    fetch('/api/toggle-parsing', { method: 'POST' })
                        .then(res => res.json())
                        .then(data => alert(data.message))
                        .catch(err => alert('Ошибка при переключении парсера.'));
                }
            });
        }
        window.addEventListener('popstate', (event) => {
            const urlParams = new URLSearchParams(window.location.search);
            const beachParam = urlParams.get('beach');

            if (beachParam) {
                const parts = beachParam.split('/');
                const beachId = parts[0];
                const isMapView = parts[1] === 'map';

                const beach = beaches.find(b => String(b.id) === String(beachId));
                if (beach) {
                    if (isMapView) focusBeachOnMap(beach);
                    else openBeachDetails(beach);
                }
            } else {
                setActiveScreen('map-screen');
            }
        });
        // --- ЛОГИКА ОТДЕЛЬНОЙ СТРАНИЦЫ ОПЕРАТОРА ---
        const operatorPage = document.getElementById('operator-page');
        if (operatorPage) {
            // Берем ID пляжа прямо из HTML
            const beachId = operatorPage.dataset.beachId;
            let selectedOperatorStatus = null;

            const statusBtns = document.querySelectorAll('.status-btn');
            const submitBtn = document.getElementById('submit-operator-data');

            // Выбор статуса
            statusBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    selectedOperatorStatus = e.currentTarget.getAttribute('data-value');
                    statusBtns.forEach(b => b.classList.remove('selected'));
                    e.currentTarget.classList.add('selected');
                    submitBtn.disabled = false;
                });
            });

            // Сохранение
            submitBtn?.addEventListener('click', () => {
                if (!selectedOperatorStatus || !beachId) return;

                submitBtn.textContent = 'Сохранение...';
                submitBtn.disabled = true;

                fetch(`/api/beach-info/${beachId}/operator-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: selectedOperatorStatus })
                })
                    .then(res => res.json())
                    .then(data => {
                        // После успешного сохранения возвращаем пользователя обратно на карту!
                        window.location.href = '/?beach=' + beachId;
                    })
                    .catch(err => {
                        console.error('Ошибка:', err);
                        alert('Произошла ошибка при сохранении на сервер.');
                        submitBtn.textContent = 'Сохранить изменения';
                        submitBtn.disabled = false;
                    });
            });
        }
    });
    updateStickyFilterOffset();
    updateScrollTopButtonVisibility();