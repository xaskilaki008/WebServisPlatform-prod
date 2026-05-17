<article id="detail-card" class="detail-card">
    <div id="gallery-main-display" class="gallery-main-display hidden">
        <img id="gallery-main-img" class="gallery-main-img hidden" src="" alt="Фото пляжа">
        <button id="main-prev-btn" class="slider-nav-btn prev hidden" onclick="changePhoto(-1, event)">‹</button>
        <button id="main-next-btn" class="slider-nav-btn next hidden" onclick="changePhoto(1, event)">›</button>
    </div>
    <div id="gallery-thumbnails" class="gallery-thumbnails"></div>

    <div class="detail-info">
        <h3 id="detail-name">Название пляжа</h3>
        <span id="detail-number" style="display:none;"></span>

        <div class="beach-meta-geo" style="margin-bottom: 10px; font-size: 13px; color: #64748b;">
            <span>Координаты: <strong id="detail-coordinates">-</strong></span>
        </div>

        <div class="data-comparison-container" id="comparison-container"
            data-is-operator="{{ $isOperator ? 'true' : 'false' }}" data-operator-beach="{{ $operatorBeachId ?? '' }}">

            <div class="data-column auto-data">
                <div class="column-header" style="color: #1e293b;">Система (DWD)</div>
                <div class="fields-list">
                    <div class="field-row"><span class="field-label" style="color: #1e293b;">Статус:</span><span
                            id="detail-category" class="category-badge"></span></div>
                    <div class="field-row"><span class="field-label" style="color: #1e293b;">Волнение:</span><span
                            id="detail-wave-text" style="color: #1e293b;">-</span></div>
                    <div class="field-row"><span class="field-label" style="color: #1e293b;">Высота:</span><span
                            id="detail-wave-height" style="color: #1e293b;">-</span></div>
                    <div class="field-row"><span class="field-label" style="color: #1e293b;">Период:</span><span
                            id="detail-wave-period" style="color: #1e293b;">-</span></div>
                    <div class="field-row"><span class="field-label" style="color: #1e293b;">Обновлено:</span><span
                            id="detail-update-time" style="color: #1e293b;">-</span></div>
                </div>
            </div>

            <div class="data-column operator-data hidden" id="operator-column-view">
                <div class="column-header" style="color: #1e293b;">Оператор</div>
                <div class="fields-list">
                    <div class="field-row">
                        <span class="field-label" style="color: #1e293b;">Фактически:</span>
                        <span class="field-value" id="operator-status-text"
                            style="color: #1e293b; font-weight: 600;">Нет данных</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label" style="color: #1e293b;">Обновлено:</span>
                        <span class="field-value" id="operator-updated-at" style="color: #1e293b;">-</span>
                    </div>
                </div>

                <a id="open-operator-link" href="#" class="operator-action-btn hidden"
                    style="display:none; text-align:center; text-decoration:none; background:#e2e8f0; color:#1e293b; padding:8px; margin-top:10px; border-radius:6px; font-weight:600;">
                    Изменить статус
                </a>
            </div>

        </div>
    </div>
</article>