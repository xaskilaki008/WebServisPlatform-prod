# Docker Deploy on Ubuntu VPS

This project is designed to run from the repository plus a server-local `.env`.
Do not commit the production `.env` or `APP_KEY`.

## Production layout

Recommended path on the VPS:

```bash
/var/www/WebServisPlatform-prod
```

Docker services:

- `app`: PHP-FPM Laravel application.
- `nginx`: public HTTP entrypoint.
- `queue`: Laravel queue worker.
- `scheduler`: Laravel scheduler worker.
- `db`: PostGIS database (`postgis/postgis` image).

The compose file passes environment variables with `env_file: .env`; the `.env`
file does not need to be copied into the Docker image.

## First deploy

```bash
sudo mkdir -p /var/www
sudo chown "$USER":"$USER" /var/www
cd /var/www
git clone <REPOSITORY_URL> WebServisPlatform-prod
cd /var/www/WebServisPlatform-prod
cp .env.docker.example .env
nano .env
```

Set at least:

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
WGRIB2_PATH=/usr/bin/wgrib2
```

Generate and keep the production app key only in the server `.env`:

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose ps
```

If this is not a fresh database, do not run seeders unless you intentionally want
to insert or refresh seed data.

## Normal update flow

Local machine:

```bash
git add .
git commit -m "Describe the deploy fix"
git push
```

VPS:

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

The homepage `/` should return a normal HTML response. If it returns an empty or
very small response, check `app` and `nginx` logs first, then run:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
```

## DWD / wgrib2 checks

The Docker image builds `wgrib2` from the official NOAA source archive and
copies it into the `app` image. The first build can take longer than usual and
requires outbound HTTPS access from the VPS during `docker compose up -d --build`.
Laravel expects:

```env
WGRIB2_PATH=/usr/bin/wgrib2
```

Check the binary:

```bash
docker compose exec app which wgrib2
docker compose exec app /usr/bin/wgrib2 -version
```

Run the parser:

```bash
docker compose exec app php artisan wave:fetch
```

Check saved forecasts:

```bash
docker compose exec app php artisan tinker --execute="echo App\\Models\\WaveForecast::count();"
```

Check API data for a beach:

```bash
curl http://127.0.0.1/api/beach-info/1
```

Parser failures are logged with the selected DWD URL, `wgrib2` path, exit code,
stdout, stderr, and saved forecast count:

```bash
docker compose logs --tail=120 app
docker compose exec app tail -n 120 storage/logs/laravel.log
```

## Static assets and slider

Production must use Vite build assets from `public/build`. The local Vite dev
marker `public/hot` is excluded from Docker context and also removed during the
image build.

Slider photos are read from:

```text
public/фотографии пляжей
```

Because the Dockerfile copies `public` into both `app` and `nginx` images, those
files must be present in the repository or otherwise delivered to `public` before
building the image.

If slider images fail on the VPS, check:

```bash
docker compose exec app ls -la "public/фотографии пляжей"
docker compose exec nginx ls -la "/var/www/html/public/фотографии пляжей"
docker compose logs --tail=80 nginx
```

## Notes

- Keep `.env` and `APP_KEY` only on the server.
- Do not replace `postgis/postgis` with plain `postgres`; migrations and geo data
  expect PostGIS.
- Run migrations after every deploy before caching config.
- If queue or scheduler fails, inspect logs after migrations and rebuild:
  `docker compose logs --tail=80 queue scheduler`.
