<?php
require_once __DIR__ . '/../../config/env.php';

class WeatherService {
    private $cacheFile;
    private $cacheTtl = 1800; // 30 minutes

    public function __construct() {
        $this->cacheFile = __DIR__ . '/../../tmp/weather_cache.json';
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
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if ($cached && $cached['lat'] == $lat && $cached['lon'] == $lon) {
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

        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) {
            throw new RuntimeException('Weather API request failed.');
        }

        $data = json_decode($response, true);

        // Save cache
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0775, true);
        }
        file_put_contents($this->cacheFile, json_encode(['lat' => $lat, 'lon' => $lon, 'data' => $data]));

        return $data;
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
        } catch (\Exception $e) {
            return [];
        }
    }
}
?>