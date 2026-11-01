<?php
require_once __DIR__ . '/../../config/env.php';

class WeatherService {
    private $cacheFile;
    private $cacheTtl = 1800; // 30 minutes
    private $pdo;

    public function __construct($pdo = null) {
        $this->cacheFile = __DIR__ . '/../../tmp/weather_cache.json';
        $this->pdo = $pdo;
    }

    /**
     * Fetch current + daily weather from Open-Meteo (no API key required).
     * @param float $lat
     * @param float $lon
     * @return array
     */
    public function getForecast($lat, $lon) {
        // Read cache
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < $this->cacheTtl)) {
            $cached = json_decode(@file_get_contents($this->cacheFile), true);
            if ($cached && isset($cached['lat'], $cached['lon']) && $cached['lat'] == $lat && $cached['lon'] == $lon) {
                return $cached['data'];
            }
        }

        $url = "https://api.open-meteo.com/v1/forecast"
            . "?latitude=" . urlencode((string)$lat)
            . "&longitude=" . urlencode((string)$lon)
            . "&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,precipitation"
            . "&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode"
            . "&timezone=auto"
            . "&forecast_days=7";

        $response = $this->httpGet($url);

        if ($response === false || trim($response) === '') {
            $stale = $this->readStaleCache($lat, $lon);
            if ($stale !== null) return $stale;
            throw new RuntimeException('Weather API request failed.');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data)) {
            $stale = $this->readStaleCache($lat, $lon);
            if ($stale !== null) return $stale;
            throw new RuntimeException('Weather API returned invalid data.');
        }

        // Save cache (non-fatal: a read-only filesystem must not break the page).
        if (!is_dir(dirname($this->cacheFile))) {
            @mkdir(dirname($this->cacheFile), 0775, true);
        }
        @file_put_contents($this->cacheFile, json_encode(['lat' => $lat, 'lon' => $lon, 'data' => $data]));

        return $data;
    }

    /** Fetch a URL with cURL (8 s timeout, require HTTP 200), falling back to file_get_contents. */
    private function httpGet($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code === 200) {
                return $body;
            }
        }

        $ctx = stream_context_create(['http' => ['timeout' => 8]]);
        return @file_get_contents($url, false, $ctx);
    }

    /** Return the last cached payload for these coords, even if expired, or null. */
    private function readStaleCache($lat, $lon) {
        if (!file_exists($this->cacheFile)) return null;
        $cached = json_decode(@file_get_contents($this->cacheFile), true);
        if ($cached && !empty($cached['data']) && isset($cached['lat'], $cached['lon']) && $cached['lat'] == $lat && $cached['lon'] == $lon) {
            return $cached['data'];
        }
        return null;
    }

    /** Return a simple summary array for the dashboard. */
    public function getCurrentSummary($lat, $lon) {
        try {
            $f = $this->getForecast($lat, $lon);
            $current = $f['current'] ?? $f['current_weather'] ?? [];
            $daily   = $f['daily'] ?? [];
            return [
                'temperature' => $current['temperature_2m'] ?? $current['temperature'] ?? null,
                'windspeed'   => $current['wind_speed_10m'] ?? $current['windspeed'] ?? null,
                'weathercode' => $current['weather_code'] ?? $current['weathercode'] ?? null,
                'humidity'    => $current['relative_humidity_2m'] ?? null,
                'high_today'  => $daily['temperature_2m_max'][0] ?? null,
                'low_today'   => $daily['temperature_2m_min'][0] ?? null,
                'precip'      => $daily['precipitation_sum'][0] ?? null,
            ];
        } catch (\Throwable $e) {
            return $this->storedSummary();
        }
    }

    /** Fallback: latest stored weather_records row (temperature + humidity). */
    private function storedSummary() {
        if (!$this->pdo) return [];
        try {
            $row = $this->pdo->query(
                "SELECT temperature, humidity FROM weather_records ORDER BY weather_date DESC, id DESC LIMIT 1"
            )->fetch();
            if (!$row) return [];
            return [
                'temperature' => $row['temperature'] !== null ? (float) $row['temperature'] : null,
                'windspeed'   => null,
                'weathercode' => null,
                'humidity'    => $row['humidity'] !== null ? (float) $row['humidity'] : null,
                'high_today'  => null,
                'low_today'   => null,
                'precip'      => null,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
?>
