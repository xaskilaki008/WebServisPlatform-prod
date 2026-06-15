# Docker-развертывание на Ubuntu VPS

Проект рассчитан на запуск из репозитория и серверного файла `.env`.
Production `.env` и `APP_KEY` нельзя коммитить в репозиторий.

## Структура production

Рекомендуемый путь на VPS:

```bash
/var/www/WebServisPlatform-prod
```

Docker-сервисы:

- `app`: Laravel-приложение на PHP-FPM.
- `nginx`: публичная HTTP-точка входа.
- `queue`: Laravel queue worker.
- `scheduler`: Laravel scheduler worker.
- `db`: база PostGIS, образ `postgis/postgis`.

Compose передает переменные окружения через `env_file: .env`; файл `.env` не
нужно копировать внутрь Docker-образа.

## Локальный запуск через Docker Desktop

Для локальной разработки на Windows/Docker Desktop используй local override
вместо серверного `.env`. Так Windows `.env` остается для `php artisan serve`, а
полный Docker-стек запускается через `app`, `nginx`, `db`, `queue` и
`scheduler`:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Локальный сайт:

```text
http://127.0.0.1:8000/
http://127.0.0.1:8000/admin
```

Эту же команду используй после изменений Blade, CSS, JS, Dockerfile,
compose-файлов, composer/npm-зависимостей или env/config-настроек, которые должны
попасть внутрь образа.

## Применение локальных UI-изменений

В этой Docker-схеме исходный код проекта не монтируется внутрь запущенных
контейнеров. Runtime-контейнер `app` также не содержит `npm`; Node используется
только на этапе `frontend` внутри Dockerfile. Поэтому команда вида
`docker compose ... exec app npm run build` завершится ошибкой `npm: executable
file not found`.

После изменения Blade, CSS или JS для Docker-сайта пересобери образы:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Затем обнови страницу в браузере через `Ctrl+F5`, чтобы не видеть старые assets
из кеша.

Если Laravel после rebuild все еще показывает старые views, очисти runtime-кеш:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app php artisan optimize:clear
```

Проверка локальных контейнеров:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml ps
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml logs --tail=80 queue scheduler
```

Остановка локального стека:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml down
```

Локальная Docker-база доступна на host-порту из `DB_FORWARD_PORT` в
`.env.docker.local`; контейнеры Laravel все равно подключаются к ней как
`db:5432`.

## Email-коды подтверждения

По умолчанию локальный Docker использует:

```env
MAIL_MAILER=log
```

В этом режиме Laravel не отправляет реальное письмо. Коды регистрации
посетителей записываются в `storage/logs/laravel.log`, а API должен сообщать,
что код записан в лог.

При локальном запуске через Docker код нужно искать в логе контейнера `app`,
а не обязательно в Windows-файле `WebServisPlatform\storage\logs\laravel.log`.
Последний шестизначный код регистрации можно вывести так:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app sh -lc "grep -oE 'Код подтверждения регистрации: [0-9]{6}' storage/logs/laravel.log | tail -1"
```

Если нужно увидеть последние строки письма целиком:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app sh -lc "grep -i -E 'From:|To:|Subject:|Код подтверждения' storage/logs/laravel.log | tail -20"
```

Для реальной отправки email настрой SMTP в активном `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

После изменения почтовых настроек очисти config cache:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
```

## Первый deploy

```bash
sudo mkdir -p /var/www
sudo chown "$USER":"$USER" /var/www
cd /var/www
git clone <REPOSITORY_URL> WebServisPlatform-prod
cd /var/www/WebServisPlatform-prod
cp .env.docker.example .env
nano .env
```

Минимально нужно настроить:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_IP_OR_DOMAIN
APP_PORT=80
DB_HOST=db
DB_PORT=5432
DB_DATABASE=webservisplatform
DB_USERNAME=webservis
DB_PASSWORD=CHANGE_ME_TO_A_STRONG_PASSWORD
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=3900
WGRIB2_PATH=/usr/bin/wgrib2
DWD_QUEUE_CONNECTION=database
DWD_QUEUE=forecast
```

Сгенерируй production app key и храни его только в серверном `.env`:

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose ps
```

Если база не свежая, не запускай seeders, если специально не хочешь добавить или
обновить seed-данные.

## Обычный update flow

На локальной машине:

```bash
git add .
git commit -m "Describe the deploy fix"
git push
```

На VPS:

```bash
cd /var/www/WebServisPlatform-prod
git fetch --all
git pull
docker compose down
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose ps
```

## Health checks

```bash
curl -I http://127.0.0.1
docker compose logs --tail=80 app
docker compose logs --tail=80 nginx
docker compose logs --tail=80 queue
docker compose logs --tail=80 scheduler
```

Парсер forecast model запускается из админ-панели через сервис `queue`. Если в
админке статус `queued` держится больше нескольких минут, worker не обрабатывает
job:

```bash
docker compose ps queue
docker compose logs --tail=80 queue
docker compose up -d queue
```

Главная `/` должна возвращать нормальный HTML. Если ответ пустой или очень
маленький, сначала проверь логи `app` и `nginx`, затем выполни:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
```

## Проверки DWD / wgrib2

Docker-образ собирает `wgrib2` из официального NOAA source archive и копирует
его в `app` image. Первая сборка может идти дольше обычного и требует outbound
HTTPS-доступа с VPS во время `docker compose up -d --build`.

Laravel ожидает:

```env
WGRIB2_PATH=/usr/bin/wgrib2
```

Проверка binary:

```bash
docker compose exec app which wgrib2
docker compose exec app test -x /usr/bin/wgrib2
docker compose exec app /usr/bin/wgrib2 -version
```

Перед полным парсером запусти диагностику окружения. Она не скачивает GRIB2 и не
меняет прогнозы:

```bash
docker compose exec app php artisan wave:diagnose
```

Запуск парсера:

```bash
docker compose exec app php artisan wave:fetch
```

Админ-панель не запускает эту команду внутри web request. Она ставит `forecast`
job в очередь, затем сервис `queue` выполняет `wave:fetch`. При здоровом ручном
запуске статус должен пройти `queued` → `running` → `success` или `failed`.

Проверка сохраненных прогнозов:

```bash
docker compose exec app php artisan tinker --execute="echo App\\Models\\WaveForecast::count();"
docker compose exec app php artisan tinker --execute="App\\Models\\WaveForecast::latest('parsed_at')->take(5)->get(['beach_id','forecast_time','model_run_at','parsed_at'])->each(fn($row) => dump($row->toArray()));"
```

Проверка API для пляжа:

```bash
curl http://127.0.0.1/api/beach-info/1
```

Ошибки парсера логируются с выбранным DWD URL, путем `wgrib2`, exit code,
stdout, stderr и числом сохраненных прогнозов:

```bash
docker compose logs --tail=120 app
docker compose exec app tail -n 120 storage/logs/laravel.log
docker compose exec app tail -n 120 storage/logs/dwd-wave-fetch.log
docker compose exec app cat storage/app/dwd_fetch_status.json
```

Админ-панель показывает то же состояние DWD через защищенные routes:

```text
GET  /admin/force-fetch/status
GET  /admin/dwd-log
POST /admin/dwd-log/clear
POST /admin/dwd-diagnose
POST /admin/force-fetch
```

Если `/admin/force-fetch/status` зависает на `queued`, проверь worker:

```bash
docker compose logs --tail=120 queue
docker compose exec app php artisan queue:failed
```

Для обычного VPS/Docker deploy держи эти значения пустыми, если только локальный
VPN/workaround не требует обратного:

```env
DWD_HTTP_PROXY=
DWD_CURL_RESOLVE=
```

## Static assets и slider

Production должен использовать Vite build assets из `public/build`. Локальный
Vite dev marker `public/hot` исключен из Docker context и дополнительно удаляется
во время build.

Фото слайдера читаются из:

```text
public/фотографии пляжей
```

Так как Dockerfile копирует `public` и в `app`, и в `nginx` images, эти файлы
должны быть в репозитории или должны быть доставлены в `public` до сборки image.

Если изображения слайдера не работают на VPS, проверь:

```bash
docker compose exec app ls -la "public/фотографии пляжей"
docker compose exec nginx ls -la "/var/www/html/public/фотографии пляжей"
docker compose logs --tail=80 nginx
```

## Заметки

- Храни `.env` и `APP_KEY` только на сервере.
- Не заменяй `postgis/postgis` на обычный `postgres`; миграции и geo data
  ожидают PostGIS.
- После каждого deploy запускай миграции до кеширования config.
- Если `queue` или `scheduler` падают, после миграций и rebuild смотри логи:
  `docker compose logs --tail=80 queue scheduler`.
