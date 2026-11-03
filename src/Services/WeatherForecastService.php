<?php
/**
 * WeatherForecastService
 * Fetches 7-day meteorological forecast + hourly data from Open-Meteo (free, no API key).
 * Caches results in the app_settings table for 1 hour to avoid hammering the API.
 */
class WeatherForecastService
{
    private $pdo;
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get 7-day forecast for a farm (by lat/lon).
     * Returns structured array with daily + hourly data.
     */
    public function getForecast(float $lat, float $lon, string $farmKey = 'default'): array
    {
        $cacheKey = "wx_forecast_{$farmKey}";
        $cached = $this->loadCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = sprintf(
            'https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f'
            . '&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,'
            . 'windspeed_10m_max,weathercode,sunrise,sunset,uv_index_max,'
            . 'precipitation_probability_max'
            . '&hourly=temperature_2m,relativehumidity_2m,windspeed_10m,'
            . 'precipitation,weathercode,apparent_temperature'
            . '&current_weather=true'
            . '&timezone=auto'
            . '&forecast_days=7',
            $lat, $lon
        );

        $raw = $this->httpGet($url);
        if ($raw === null) {
            return $this->fallbackData($lat, $lon);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['daily'])) {
            return $this->fallbackData($lat, $lon);
        }

        $result = $this->formatResponse($decoded);
        $this->saveCache($cacheKey, $result);
        return $result;
    }

    /**
     * Get forecast for a specific farm record from the DB.
     */
    public function getForecastForFarm(int $farmId): array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT id, name, latitude, longitude FROM farms WHERE id = ?"
            );
            $st->execute([$farmId]);
            $farm = $st->fetch();
        } catch (\Throwable $e) {
            $farm = null;
        }

        if (!$farm) {
            // Fall back to env defaults
            require_once __DIR__ . '/../../config/env.php';
            $lat = (float) getEnvVar('DEFAULT_LAT', -15.3875);
            $lon = (float) getEnvVar('DEFAULT_LON', 28.3228);
            return $this->getForecast($lat, $lon, 'default');
        }

        $lat = (float) ($farm['latitude'] ?? -15.3875);
        $lon = (float) ($farm['longitude'] ?? 28.3228);
        return $this->getForecast($lat, $lon, 'farm_' . $farmId);
    }

    /**
     * Get all farms with their latest cached forecast summary.
     */
    public function getAllFarmForecasts(): array
    {
        try {
            $farms = $this->pdo->query(
                "SELECT id, name, latitude, longitude FROM farms ORDER BY name"
            )->fetchAll();
        } catch (\Throwable $e) {
            $farms = [];
        }

        $results = [];
        foreach ($farms as $farm) {
            $lat = (float) ($farm['latitude'] ?? -15.3875);
            $lon = (float) ($farm['longitude'] ?? 28.3228);
            $data = $this->getForecast($lat, $lon, 'farm_' . $farm['id']);
            $results[] = [
                'farm_id'   => (int) $farm['id'],
                'farm_name' => $farm['name'],
                'current'   => $data['current'] ?? null,
                'today'     => $data['daily'][0] ?? null,
                'forecast'  => $data,
            ];
        }
        return $results;
    }

    // -----------------------------------------------------------------------
    // Formatting helpers
    // -----------------------------------------------------------------------

    private function formatResponse(array $d): array
    {
        $daily   = $d['daily'] ?? [];
        $hourly  = $d['hourly'] ?? [];
        $current = $d['current_weather'] ?? [];

        $days = [];
        $count = count($daily['time'] ?? []);
        for ($i = 0; $i < $count; $i++) {
            $code = (int) ($daily['weathercode'][$i] ?? 0);
            $days[] = [
                'date'           => $daily['time'][$i] ?? '',
                'temp_max'       => $daily['temperature_2m_max'][$i] ?? null,
                'temp_min'       => $daily['temperature_2m_min'][$i] ?? null,
                'precipitation'  => $daily['precipitation_sum'][$i] ?? 0,
                'wind_max'       => $daily['windspeed_10m_max'][$i] ?? null,
                'weathercode'    => $code,
                'condition'      => self::wmoDescription($code),
                'icon'           => self::wmoIcon($code),
                'sunrise'        => $daily['sunrise'][$i] ?? null,
                'sunset'         => $daily['sunset'][$i] ?? null,
                'uv_index'       => $daily['uv_index_max'][$i] ?? null,
                'rain_chance'    => $daily['precipitation_probability_max'][$i] ?? null,
                'farm_advice'    => self::farmAdvice($code, $daily['precipitation_sum'][$i] ?? 0, $daily['windspeed_10m_max'][$i] ?? 0),
            ];
        }

        // Build hourly for today (first 24 entries)
        $hourlyToday = [];
        for ($i = 0; $i < 24 && $i < count($hourly['time'] ?? []); $i++) {
            $hCode = (int) ($hourly['weathercode'][$i] ?? 0);
            $hourlyToday[] = [
                'time'        => substr($hourly['time'][$i] ?? '', 11, 5),
                'temp'        => $hourly['temperature_2m'][$i] ?? null,
                'feels_like'  => $hourly['apparent_temperature'][$i] ?? null,
                'humidity'    => $hourly['relativehumidity_2m'][$i] ?? null,
                'wind'        => $hourly['windspeed_10m'][$i] ?? null,
                'precipitation'=> $hourly['precipitation'][$i] ?? 0,
                'weathercode' => $hCode,
                'icon'        => self::wmoIcon($hCode),
            ];
        }

        $currentCode = (int) ($current['weathercode'] ?? 0);
        return [
            'current' => [
                'temp'        => $current['temperature'] ?? null,
                'wind'        => $current['windspeed'] ?? null,
                'wind_dir'    => $current['winddirection'] ?? null,
                'weathercode' => $currentCode,
                'condition'   => self::wmoDescription($currentCode),
                'icon'        => self::wmoIcon($currentCode),
                'is_day'      => (bool) ($current['is_day'] ?? 1),
                'time'        => $current['time'] ?? '',
            ],
            'daily'         => $days,
            'hourly_today'  => $hourlyToday,
            'timezone'      => $d['timezone'] ?? 'UTC',
            'fetched_at'    => date('Y-m-d H:i:s'),
        ];
    }

    // -----------------------------------------------------------------------
    // WMO weather interpretation codes → description + emoji icon
    // -----------------------------------------------------------------------

    public static function wmoDescription(int $code): string
    {
        $map = [
            0 => 'Clear sky', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
            45 => 'Fog', 48 => 'Icy fog',
            51 => 'Light drizzle', 53 => 'Moderate drizzle', 55 => 'Dense drizzle',
            61 => 'Slight rain', 63 => 'Moderate rain', 65 => 'Heavy rain',
            71 => 'Slight snow', 73 => 'Moderate snow', 75 => 'Heavy snow',
            77 => 'Snow grains',
            80 => 'Slight showers', 81 => 'Moderate showers', 82 => 'Violent showers',
            85 => 'Slight snow showers', 86 => 'Heavy snow showers',
            95 => 'Thunderstorm', 96 => 'Thunderstorm with hail', 99 => 'Thunderstorm with heavy hail',
        ];
        return $map[$code] ?? "Weather code $code";
    }

    public static function wmoIcon(int $code): string
    {
        if ($code === 0 || $code === 1) return '☀️';
        if ($code === 2) return '⛅';
        if ($code === 3) return '☁️';
        if ($code === 45 || $code === 48) return '🌫️';
        if ($code >= 51 && $code <= 55) return '🌦️';
        if ($code >= 61 && $code <= 65) return '🌧️';
        if ($code >= 71 && $code <= 77) return '❄️';
        if ($code >= 80 && $code <= 82) return '🌧️';
        if ($code >= 85 && $code <= 86) return '🌨️';
        if ($code >= 95) return '⛈️';
        return '🌤️';
    }

    /**
     * Generate practical farm advice based on weather conditions.
     */
    public static function farmAdvice(int $code, float $rain, float $wind): string
    {
        if ($code >= 95) {
            return '⚠️ Severe weather: secure equipment, move livestock to shelter, postpone field operations.';
        }
        if ($code >= 61 && $code <= 65) {
            return '🌧️ Rain expected: good for soil moisture, avoid spraying pesticides/fertiliser today.';
        }
        if ($code >= 80 && $code <= 82) {
            return '🌧️ Rain showers: delay harvesting if possible, check drainage channels.';
        }
        if ($rain > 5) {
            return '💧 Significant rainfall forecast: ideal for planting, reduce irrigation, check for waterlogging.';
        }
        if ($rain > 0 && $rain <= 5) {
            return '🌱 Light rain: natural irrigation boost — reduce scheduled watering.';
        }
        if ($wind > 40) {
            return '💨 Strong winds: secure structures and polytunnels, delay aerial spraying.';
        }
        if ($code === 0 || $code === 1) {
            return '☀️ Clear skies: ideal for harvesting, drying crops, field operations, and spraying.';
        }
        if ($code === 2 || $code === 3) {
            return '⛅ Cloudy: good working conditions, moderate evaporation — monitor soil moisture.';
        }
        return '✅ Conditions acceptable for general farm operations.';
    }

    // -----------------------------------------------------------------------
    // HTTP + cache helpers
    // -----------------------------------------------------------------------

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'FFMS/1.0 (farm-management-system)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err || $body === false) {
                error_log("WeatherForecastService cURL error: $err");
                return null;
            }
            return (string) $body;
        }

        // Fall back to file_get_contents if allow_url_fopen is on
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'FFMS/1.0']]);
        $body = @file_get_contents($url, false, $ctx);
        return ($body !== false) ? $body : null;
    }

    private function loadCache(string $key): ?array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT value, updated_at FROM app_settings WHERE key = ?"
            );
            $st->execute([$key]);
            $row = $st->fetch();
            if (!$row) return null;
            $age = time() - strtotime($row['updated_at']);
            if ($age > self::CACHE_TTL) return null;
            $data = json_decode($row['value'], true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function saveCache(string $key, array $data): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO app_settings (key, value, updated_at)
                 VALUES (?, ?, CURRENT_TIMESTAMP)
                 ON CONFLICT (key) DO UPDATE
                 SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP"
            )->execute([$key, json_encode($data)]);
        } catch (\Throwable $e) {
            // Cache write failure is non-fatal
        }
    }

    /**
     * Fallback when the API is unreachable — returns empty structure.
     */
    private function fallbackData(float $lat, float $lon): array
    {
        return [
            'current'      => null,
            'daily'        => [],
            'hourly_today' => [],
            'timezone'     => 'UTC',
            'fetched_at'   => null,
            'error'        => 'Weather data temporarily unavailable. Check network connectivity.',
            'lat'          => $lat,
            'lon'          => $lon,
        ];
    }
}
