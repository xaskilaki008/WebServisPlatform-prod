# Docker Deploy На Ubuntu VPS

Инструкция рассчитана на чистый Ubuntu-сервер. Домен и HTTPS можно подключить после проверки запуска по IP и порту.

## 1. Установить Docker

```bash
sudo apt update
sudo apt install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Проверьте:

```bash
docker --version
docker compose version
```

## 2. Загрузить проект

Лучший вариант - Git:

```bash
sudo mkdir -p /var/www
sudo chown "$USER":"$USER" /var/www
cd /var/www
git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ> WebServisPlatform
cd WebServisPlatform
```

Если Git пока нет, можно загрузить архив проекта и распаковать его в `/var/www/WebServisPlatform`.

## 3. Настроить `.env`

```bash
cp .env.docker.example .env
nano .env
```

Минимально поменяйте:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://ВАШ_IP_ИЛИ_ДОМЕН:8080
APP_PORT=8080
DB_PASSWORD=СЛОЖНЫЙ_ПАРОЛЬ
```

Для домена с обычным HTTP-портом позже можно поставить:

```env
APP_URL=http://example.com
APP_PORT=80
```

## 4. Собрать и запустить

```bash
docker compose up -d --build
```

Первый запуск может занять несколько минут: Docker скачает PHP, Node, Nginx и PostGIS-образы, установит зависимости и соберет Vite assets.

## 5. Инициализировать Laravel

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

После этого откройте:

```text
http://ВАШ_IP:8080
```

## 6. Полезные команды

Посмотреть контейнеры:

```bash
docker compose ps
```

Логи:

```bash
docker compose logs -f app
docker compose logs -f nginx
```

Остановить:

```bash
docker compose down
```

Пересобрать после обновления кода:

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## 7. DWD И wgrib2

В проекте есть команда:

```bash
docker compose exec app php artisan wave:fetch
```

Она требует `wgrib2`. Сейчас Dockerfile не устанавливает `wgrib2` автоматически, потому что официальный пакет доступен не во всех Ubuntu/Debian репозиториях. Если DWD-парсер нужен на сервере, нужно:

- собрать или установить `wgrib2` внутри app-образа;
- или смонтировать готовый бинарник в контейнер;
- затем прописать путь в `.env` через `WGRIB2_PATH`.

Без `wgrib2` сайт и обычная Laravel-часть запускаются, но `wave:fetch` не сможет извлекать данные из GRIB-файлов.

## 8. HTTPS

Для продакшена лучше поставить внешний reverse proxy с HTTPS, например Nginx на сервере или Traefik/Caddy. На первом этапе проще проверить приложение по `http://IP:8080`, а потом подключить домен и сертификат.
