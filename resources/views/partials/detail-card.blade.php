<article id="detail-card" class="detail-card">
    <div id="gallery-main-display" class="gallery-main-display hidden">
        <div id="skeleton-main-display" class="skeleton skeleton-main-display hidden"></div>
        <img id="gallery-main-img" class="gallery-main-img hidden" src="" alt="Фото пляжа">
        <button id="main-prev-btn" class="slider-nav-btn prev hidden" onclick="changePhoto(-1, event)">‹</button>
        <button id="main-next-btn" class="slider-nav-btn next hidden" onclick="changePhoto(1, event)">›</button>
        <div id="gallery-photo-number" class="photo-number-label hidden"></div>
    </div>
    <div id="gallery-thumbnails" class="gallery-thumbnails"></div>

    <div class="detail-info">
        <h3 id="detail-name">Название пляжа</h3>
        <span id="detail-number" style="display:none;"></span>

        <div class="beach-meta-geo" style="margin-bottom: 10px; font-size: 13px; color: #64748b;">
            <span>Координаты: <strong id="detail-coordinates">-</strong></span>
            <button id="detail-map-button" class="inline-map-btn"
                style="margin-left: 10px; display: none;">Показать</button>
        </div>

        <div class="data-comparison-container" id="comparison-container"
            data-is-operator="{{ $isOperator ? 'true' : 'false' }}" data-operator-beach="{{ $operatorBeachId ?? '' }}">

            <div class="data-column auto-data">
                <div class="column-header">Система</div>
                <div class="fields-list">
                    <div class="field-row"><span class="field-label">Статус:</span><span id="detail-category"
                            class="category-badge"></span></div>
                    <div class="field-row"><span class="field-label">Волнение:</span><span><span
                                id="detail-wave-level"></span> (<span id="detail-wave-text"></span>)</span></div>
                    <div class="field-row"><span class="field-label">Высота:</span><span
                            id="detail-wave-height">-</span></div>
                    <div class="field-row"><span class="field-label">Период:</span><span
                            id="detail-wave-period">-</span></div>
                    <div class="field-row"><span class="field-label">Обновлено:</span><span
                            id="detail-update-time">-</span></div>
                </div>
            </div>

            <div class="data-column operator-data hidden" id="operator-column-view" style="display: none;">
                <div class="column-header">Оператор</div>
                <div class="fields-list">
                    <div class="field-row">
                        <span class="field-label">Фактически:</span>
                        <span class="field-value" id="operator-status-text">Нет данных</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Обновлено:</span>
                        <span class="field-value" id="operator-updated-at">-</span>
                    </div>
                </div>

                <a id="open-operator-link" href="#" class="operator-action-btn hidden"
                    style="display:none; text-align:center; text-decoration:none; background:#e2e8f0; color:#1e293b; padding:8px; margin-top:12px; border-radius:6px; font-weight:600; font-size:13px;">
                    Изменить статус
                </a>
            </div>

        </div>
    </div>
</article>