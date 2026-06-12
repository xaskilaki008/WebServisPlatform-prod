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

## Local Docker Desktop run

For local Windows/Docker Desktop development, use the local override file instead
of the server `.env`. This keeps the Windows `.env` for `php artisan serve` and
runs the full Docker stack with `app`, `nginx`, `db`, `queue`, and `scheduler`:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Open the local Docker site at:

```text
http://127.0.0.1:8000/
http://127.0.0.1:8000/admin
```

Use the same command after changing Dockerfile, compose files, composer/npm
dependencies, or environment/configuration that must be baked into the image.
For routine Blade, CSS, or JS edits, use the faster local cycle below.

## Fast local change cycle

If the local Docker stack is already running, do not rebuild the whole
application for every UI edit. Use targeted commands instead.

After changing only Blade templates:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app php artisan view:clear
```

After changing only CSS or JS:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app npm run build
```

After changing Blade plus CSS/JS:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app npm run build
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml exec app php artisan view:clear
```

Then refresh the browser with `Ctrl+F5` to avoid stale built assets.

Use the full rebuild again when changing Docker, dependency, or environment
files:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Check local containers:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml ps
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml logs --tail=80 queue scheduler
```

Stop the local stack:

```powershell
docker compose --env-file .env.docker.local -f docker-compose.yml -f docker-compose.local.yml down
```

The local Docker database is exposed on the host port configured by
`DB_FORWARD_PORT` in `.env.docker.local`; Laravel containers still connect to it
as `db:5432`.

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
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=3900
WGRIB2_PATH=/usr/bin/wgrib2
DWD_QUEUE_CONNECTION=database
DWD_QUEUE=forecast
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

The forecast model parser is started from the admin panel through the `queue`
service. If the admin panel shows `queued` for more than a few minutes, the
worker is not processing the job:

```bash
docker compose ps queue
docker compose logs --tail=80 queue
docker compose up -d queue
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
docker compose exec app test -x /usr/bin/wgrib2
docker compose exec app /usr/bin/wgrib2 -version
```

Run the environment diagnostics before the full parser. This does not download
GRIB2 files and does not change forecasts:

```bash
docker compose exec app php artisan wave:diagnose
```

Run the parser:

```bash
docker compose exec app php artisan wave:fetch
```

The admin panel does not run this command inside a web request. It queues a
`forecast` job, then the `queue` service executes `wave:fetch`. During a healthy
manual run the status should move from `queued` to `running` and then to
`success` or `failed`.

Check saved forecasts:

```bash
docker compose exec app php artisan tinker --execute="echo App\\Models\\WaveForecast::count();"
docker compose exec app php artisan tinker --execute="App\\Models\\WaveForecast::latest('parsed_at')->take(5)->get(['beach_id','forecast_time','model_run_at','parsed_at'])->each(fn($row) => dump($row->toArray()));"
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
docker compose exec app tail -n 120 storage/logs/dwd-wave-fetch.log
docker compose exec app cat storage/app/dwd_fetch_status.json
```

The admin panel exposes the same DWD state through protected routes:

```text
GET  /admin/force-fetch/status
GET  /admin/dwd-log
POST /admin/dwd-log/clear
POST /admin/dwd-diagnose
POST /admin/force-fetch
```

If `/admin/force-fetch/status` stays at `queued`, inspect the worker:

```bash
docker compose logs --tail=120 queue
docker compose exec app php artisan queue:failed
```

For normal VPS/Docker deployment keep these empty unless a local development VPN
requires a workaround:

```env
DWD_HTTP_PROXY=
DWD_CURL_RESOLVE=
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
