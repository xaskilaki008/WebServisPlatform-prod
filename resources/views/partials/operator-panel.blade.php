<div id="operator-panel-modal" class="image-overlay hidden">
    <div class="operator-panel-wrapper">
        <button id="close-operator-btn" class="close-popup-btn">&times;</button>

        <div class="operator-header">
            <h2>Панель оператора</h2>
            <p id="operator-panel-beach-name">Название пляжа</p>
        </div>

        <div class="operator-body">
            <p class="instruction">Укажите фактическое состояние акватории:</p>

            <div class="beaufort-grid">
                <button class="status-btn" data-value="0"><span class="status-icon">🪞</span> 0 баллов</button>
                <button class="status-btn" data-value="1"><span class="status-icon">💧</span> 1 балл</button>
                <button class="status-btn" data-value="2"><span class="status-icon">🌊</span> 2 балла</button>
                <button class="status-btn" data-value="3"><span class="status-icon">🌊</span> 3 балла</button>
                <button class="status-btn" data-value="4"><span class="status-icon">🌊</span> 4 балла</button>
                <button class="status-btn" data-value="5"><span class="status-icon">🌊</span> 5 баллов</button>
                <button class="status-btn hazard-btn" data-value="hazard"><span class="status-icon">🛢️</span>
                    Опасность</button>
            </div>

            <div class="operator-actions">
                <button id="submit-operator-data" class="save-btn" disabled>Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>