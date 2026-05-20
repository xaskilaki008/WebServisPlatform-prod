# Docker Deploy на Ubuntu VPS

Инструкция рассчитана на чистый Ubuntu-сервер. Сначала проверьте запуск по IP и порту, потом подключайте домен и HTTPS.

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

Проверка:

```bash
docker --version
docker compose version
```

## 2. Загрузить проект

```bash
sudo mkdir -p /var/www
sudo chown "$USER":"$USER" /var/www
cd /var/www
git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ> WebServisPlatform
cd WebServisPlatform
```

Если Git пока нет, загрузите архив проекта и распакуйте его в `/var/www/WebServisPlatform`.

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

## 6. Обновление проекта

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## 7. Полезные команды

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f nginx
docker compose down
```

## 8. DWD и wgrib2

Команда DWD:

```bash
docker compose exec app php artisan wave:fetch
```

Она требует бинарник `wgrib2`. В `.env.docker.example` указано:

```env
WGRIB2_PATH=wgrib2
```

Dockerfile пока не устанавливает `wgrib2` автоматически, потому что готовый пакет доступен не во всех Debian/Ubuntu репозиториях. Если парсер нужен на сервере, добавьте бинарник `wgrib2` в app-образ или смонтируйте его внутрь контейнера и пропишите путь в `WGRIB2_PATH`.

## 9. HTTPS

Для продакшена лучше поставить внешний reverse proxy с HTTPS, например Nginx на сервере, Caddy или Traefik. На первом этапе проще проверить приложение по `http://IP:8080`.
