# Доработка Detail Card, Popup, Цветов и DWD Backend

## Summary
Обновить UI карточки пляжа и исправить DWD backend так, чтобы система выбирала актуальный цикл поставщика: до 12:00 использовать данные цикла 00:00, после 12:00 использовать цикл 12:00. Также добавить на фронт `wave_direction`, `air_temp`, `water_temp`.

## Backend Changes
- Добавить миграцию для `wave_forecasts`:
  - `model_run_at` timestamp nullable: фактический цикл поставщика, 00:00 или 12:00.
  - `model_run_hour` unsigned tiny integer nullable: `0` или `12`.
  - уникальный индекс на `beach_id + model_run_at`, чтобы один цикл для пляжа обновлялся, а не плодил дубли.
  - оставить `forecast_time` для обратной совместимости, но новые выборки строить по `model_run_at`.
- Обновить `app/Models/WaveForecast.php`:
  - добавить `air_temp`, `water_temp`, `model_run_at`, `model_run_hour` в `$fillable`.
  - добавить casts для новых полей.
- Исправить `app/Console/Commands/FetchDwdWaveData.php`:
  - вычислять текущий DWD cycle: если текущий час `< 12`, cycle `00`; иначе `12`.
  - использовать каталог DWD `/grib/{00|12}/{parameter}/`, а не всегда `/grib/00/...`.
  - regex должен искать файл выбранного цикла, а не только `_000`.
  - при сохранении писать `model_run_at` как сегодняшнюю дату с часом 00:00 или 12:00.
  - `updateOrCreate` делать по `beach_id + model_run_at`.
- Исправить выбор данных:
  - В `routes/api.php` endpoint `/api/beach-info/{id}` сейчас не использует `BeachController@getInfo()`, поэтому либо перевести route на контроллер, либо перенести логику выбора туда же. Предпочтительно: подключить `BeachController@getInfo`.
  - В `BeachController@getInfo($id)` выбирать прогноз по текущему cycle (`model_run_at <= currentCycle`) с сортировкой `model_run_at desc`.
  - В JSON вернуть `wave_direction`, `air_temp`, `water_temp`, `forecast_time`, `model_run_at`, `model_run_hour`.

## Frontend/UI Changes
- В `resources/css/app.css` заменить яркий `yellow` на приглушённый `#e8d44a` для `--status-caution`, `.badge-caution`, `.operator-warning-panel`, `.operator-save-button`.
- В `resources/js/app.js` в `buildPopupContent()` вынести номер пляжа в `.popup-beach-number`; в CSS сделать его меньше и мягче внутри `.leaflet-popup-content-wrapper`.
- Сделать `#scroll-down-btn` таким же по виду, как `.scroll-top-button`, и поставить в одну вертикальную линию справа.
- В `detail-card.blade.php` добавить общий `.detail-content-grid`:
  - слева `.detail-main-tables` с `.detail-tables-grid`;
  - справа `#operator-column-view`.
  - `operator-column-view` скрывать обычному пользователю, если `operator_status` пустой.
- В detail tables добавить строки:
  - `Направление волны` → `detail-wave-direction`
  - `Температура воздуха` → `detail-air-temp`
  - `Температура воды` → `detail-water-temp`
- Нижний блок обновления:
  - `Обновлено DWD` → `detail-update-time`
  - `Обновлено Оператором пляжа` → `operator-update-time`
  - `Доступность` перенести вниз под временем обновления.
- В `resources/js/app.js` после fetch `/api/beach-info/{id}` заполнять новые поля и ставить `-`, если значение `null`.

## Test Plan
- Запустить миграции на тестовой БД.
- Проверить `wave:fetch` при смоделированном времени:
  - 07:00 выбирает DWD каталог `00` и сохраняет `model_run_hour = 0`.
  - 17:00 выбирает DWD каталог `12` и сохраняет `model_run_hour = 12`.
- Проверить `/api/beach-info/{id}`:
  - возвращает прогноз текущего цикла;
  - возвращает `wave_direction`, `air_temp`, `water_temp`.
- Запустить `npm.cmd run build`.
- Проверить detail card:
  - новые DWD поля видны;
  - operator block параллелен основной таблице;
  - обычный пользователь не видит пустой operator block;
  - доступность находится под временем обновления.
- Проверить Leaflet popup и обе scroll-кнопки визуально.

## Assumptions
- Правило 00/12 применяется в timezone приложения (`Europe/Moscow` в текущем окружении), как в примерах “07:00 → 00” и “17:00 → 12”.
- Если данные цикла 12:00 ещё не скачаны, API должен взять последний доступный `model_run_at <= текущий cycle`, а не будущий или пустой прогноз.
- `air_temp` и `water_temp` уже есть в таблице `wave_forecasts`, но сейчас не заполняются парсером; план добавляет их в модель/API/UI, а отдельное подключение DWD temperature-параметров в парсер нужно делать только если поставщик реально отдаёт эти параметры в используемом каталоге.
