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