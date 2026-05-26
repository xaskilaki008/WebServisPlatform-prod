<?php

return [
    'ewam_base_url' => env(
        'DWD_EWAM_BASE_URL',
        'https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib'
    ),
    'http_proxy' => env('DWD_HTTP_PROXY'),
    'curl_resolve' => env('DWD_CURL_RESOLVE'),
    'auto_curl_resolve' => filter_var(env('DWD_AUTO_CURL_RESOLVE', true), FILTER_VALIDATE_BOOL),
    'http_retries' => (int) env('DWD_HTTP_RETRIES', 3),
    'retry_delay_ms' => (int) env('DWD_HTTP_RETRY_DELAY_MS', 2000),
    'connect_timeout' => (int) env('DWD_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('DWD_HTTP_TIMEOUT', 30),
];
