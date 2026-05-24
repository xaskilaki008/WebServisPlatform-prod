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

    <div class="detail-data-grid">
        <section class="detail-group-block detail-data-section">
            <h3 class="detail-section-title">Автоматические гидроданные</h3>

            <div class="detail-tables-grid">
                <div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Уровень волнения:</div>
                        <div class="detail-val" id="detail-wave-level">-</div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Категория:</div>
                        <div class="detail-val"><span id="detail-category" class="category-badge">-</span></div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Направление волны:</div>
                        <div class="detail-val" id="detail-wave-direction">-</div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Описание моря:</div>
                        <div class="detail-val" id="detail-wave-text">Нет данных</div>
                    </div>
                </div>

                <div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Высота волны:</div>
                        <div class="detail-val" id="detail-wave-height">-</div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Период волны:</div>
                        <div class="detail-val" id="detail-wave-period">-</div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Температура воздуха:</div>
                        <div class="detail-val" id="detail-air-temp">-</div>
                    </div>
                    <div class="detail-row-table">
                        <div class="detail-lbl">Температура воды:</div>
                        <div class="detail-val" id="detail-water-temp">-</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="operator-column-view" class="detail-group-block detail-data-section operator-detail-block">
            <h3 class="detail-section-title">Данные оператора</h3>

            <div id="operator-empty-message" class="operator-empty-message">Данные оператора отсутствуют</div>

            <div id="operator-log-table">
                <div class="detail-row-table">
                    <div class="detail-lbl">Статус:</div>
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
                    <div class="detail-lbl">Предупреждение:</div>
                    <div class="detail-val" id="operator-warning-value">-</div>
                </div>
                <div class="detail-row-table">
                    <div class="detail-lbl">Доступность:</div>
                    <div class="detail-val" id="operator-access-value">-</div>
                </div>
                <div class="detail-row-table">
                    <div class="detail-lbl">Время отправки:</div>
                    <div class="detail-val" id="operator-update-time">-</div>
                </div>
                <div class="detail-row-table hidden" id="operator-stale-row">
                    <div class="detail-lbl">Предупреждение:</div>
                    <div class="detail-val operator-stale-message">Актуальные операторские данные отсутствуют или устарели</div>
                </div>
            </div>

            <div id="operator-contact-block" class="operator-contact-block hidden">
                <h4 class="operator-contact-title">Контакты оператора</h4>
                <div id="operator-contact-name-row" class="operator-contact-row hidden">
                    <span>Имя</span>
                    <strong id="operator-contact-name">-</strong>
                </div>
                <div id="operator-contact-phone-row" class="operator-contact-row hidden">
                    <span>Рабочий телефон</span>
                    <strong id="operator-contact-phone">-</strong>
                </div>
            </div>

            <div class="detail-row-table">
                <div class="detail-lbl">Управление:</div>
                <div class="detail-val">
                    <a id="open-operator-link" class="action-button primary small hidden" href="#">Изменить статус</a>
                </div>
            </div>
        </section>
    </div>

    <div class="detail-group-block detail-update-block">
        <div class="detail-row-table">
            <div class="detail-lbl">Прогноз DWD на:</div>
            <div class="detail-val" id="detail-forecast-time">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Запуск модели DWD:</div>
            <div class="detail-val" id="detail-model-run-time">-</div>
        </div>
        <div class="detail-row-table">
            <div class="detail-lbl">Обработано сервером:</div>
            <div class="detail-val" id="detail-parsed-time">-</div>
        </div>
    </div>
</article>
