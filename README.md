# WebServisPlatform

Laravel-приложение для карты пляжей, операторской панели и загрузки волновых данных.

## Быстрый запуск через Docker

1. Скопируйте docker-env:

```bash
cp .env.docker.example .env
```

2. Заполните в `.env` минимум:

```env
APP_URL=http://localhost:8080
DB_PASSWORD=change_me
```

3. Соберите и запустите контейнеры:

```bash
docker compose up -d --build
```

4. Сгенерируйте ключ и примените миграции:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

5. Откройте приложение:

```text
http://localhost:8080
```

Подробная инструкция для Ubuntu/VPS: [docs/docker-deploy.md](docs/docker-deploy.md).

## Сервисы Docker

- `nginx` - веб-сервер, порт задается через `APP_PORT`.
- `app` - PHP-FPM с Laravel.
- `queue` - обработчик очереди Laravel.
- `scheduler` - `php artisan schedule:work`.
- `db` - PostgreSQL + PostGIS.

## Важно про DWD/wgrib2

Команда `wave:fetch` использует `WGRIB2_PATH`. В Docker по умолчанию стоит:

```env
WGRIB2_PATH=wgrib2
```

Если парсер DWD нужен на сервере, добавьте бинарник `wgrib2` в образ или смонтируйте его внутрь контейнера и укажите путь в `.env`.
