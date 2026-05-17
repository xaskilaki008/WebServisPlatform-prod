<article class="detail-card">
    <h2 id="detail-name">Пляж не выбран</h2>
    <div class="detail-number-plain">ID пляжа: <span id="detail-number">-</span></div>

    <div class="gallery-container">
        <div id="gallery-thumbnails" class="thumbnails-line"></div>

        <div id="gallery-main-display" class="main-photo-box hidden">
            <div class="main-photo-wrapper">
                <div id="skeleton-main-display" class="skeleton skeleton-main-photo hidden"></div>

                <img id="gallery-main-img" src="" alt="Фото пляжа">

                <div class="gallery-controls">
                    <button id="main-prev-btn" class="slider-nav-btn prev" onclick="changePhoto(-1, event)">‹</button>
                    <div id="gallery-photo-number" class="photo-number-label"></div>
                    <button id="main-next-btn" class="slider-nav-btn next" onclick="changePhoto(1, event)">›</button>
                </div>
            </div>
        </div>
    </div>
    <div class="detail-tables-grid">
    <div class="detail-group-block">
        <div class="detail-row-table">
            <div class="detail-lbl">Уровень волнения:</div>
            <div class="detail-val" id="detail-wave-level">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Категория:</div>
            <div class="detail-val"><span id="detail-category" class="category-badge">-</span></div>
        </div>
    </div>
    <div class="detail-group-block">
        <div class="detail-row-table">
            <div class="detail-lbl">Описание моря:</div>
            <div class="detail-val" id="detail-wave-text">Нет данных</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Высота волны:</div>
            <div class="detail-val" id="detail-wave-height">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Период волны:</div>
            <div class="detail-val" id="detail-wave-period">-</div>
        </div>
    </div>
    </div>
    <div id="operator-column-view" class="detail-group-block operator-detail-block hidden">
        <div class="detail-row-table">
            <div class="detail-lbl">Оператор:</div>
            <div class="detail-val" id="operator-status-value">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Категория:</div>
            <div class="detail-val" id="operator-category-value">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Направление:</div>
            <div class="detail-val" id="operator-direction-value">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Период:</div>
            <div class="detail-val" id="operator-period-value">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Доступность:</div>
            <div class="detail-val" id="operator-access-value">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Предупреждение:</div>
            <div class="detail-val" id="operator-warning-value">-</div>
        </div>
        <div class="detail-row-table hidden" id="operator-stale-row">
            <div class="detail-lbl">Данные:</div>
            <div class="detail-val">Данные от оператора не обновлялись более 1 часа. Показывается автоматический прогноз.</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Управление:</div>
            <div class="detail-val">
                <a id="open-operator-link" class="action-button primary small hidden" href="#">Изменить статус</a>
            </div>
        </div>
    </div>
    <div class="detail-group-block">
        <div class="detail-row-table">
            <div class="detail-lbl">Обновлено (DWD):</div>
            <div class="detail-val" id="detail-update-time">-</div>
        </div>
    </div>
</article>
