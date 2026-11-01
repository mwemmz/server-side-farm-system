<?php
/**
 * GeoJsonHelper — validate and measure field boundaries.
 *
 * The application stores a canonical GeoJSON Polygon (JSONB) for a field and a
 * computed area in hectares. Nothing here trusts the browser: coordinates are
 * range-checked, rings are closed, point counts are capped, and holes are
 * subtracted before the area is reported.
 */
class GeoJsonHelper
{
    public const MAX_POINTS = 5000;

    /**
     * Normalise any supported boundary payload.
     *
     * @param mixed $raw JSON string, array, or null/empty
     * @return array{geojson:?string,hectares:?float,centroid:?array,error:?string}
     */
    public static function normalise($raw): array
    {
        $empty = ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => null];
        if ($raw === null) return $empty;
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') return $empty;
            if (strlen($raw) > 500000) {
                return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary data is too large.'];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary must be valid GeoJSON.'];
            }
            $raw = $decoded;
        }
        if (!is_array($raw)) {
            return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary must be valid GeoJSON.'];
        }

        $geometry = self::extractPolygon($raw);
        if ($geometry === null) {
            return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary must be a Polygon or a Feature containing one.'];
        }

        $rings = [];
        $points = 0;
        foreach (array_slice($geometry, 0, 20) as $ring) {
            if (!is_array($ring)) {
                return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary ring is malformed.'];
            }
            $clean = self::cleanRing($ring);
            if ($clean === null) {
                return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Each boundary ring needs at least 3 valid coordinates.'];
            }
            $points += count($clean);
            if ($points > self::MAX_POINTS) {
                return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary has too many points (max ' . self::MAX_POINTS . ').'];
            }
            $rings[] = $clean;
        }
        if (!$rings) {
            return ['geojson' => null, 'hectares' => null, 'centroid' => null, 'error' => 'Boundary needs an outer ring.'];
        }

        $area = self::ringArea($rings[0]);
        for ($i = 1; $i < count($rings); $i++) {
            $area -= self::ringArea($rings[$i]);
        }
        $area = max(0.0, $area);

        return [
            'geojson'  => json_encode(['type' => 'Polygon', 'coordinates' => $rings]),
            'hectares' => round($area / 10000, 4),
            'centroid' => self::centroid($rings[0]),
            'error'    => null,
        ];
    }

    /** Extract a raw Polygon coordinates array from Polygon/Feature/FeatureCollection. */
    private static function extractPolygon(array $node): ?array
    {
        $type = strtolower((string) ($node['type'] ?? ''));
        if ($type === 'polygon') {
            return isset($node['coordinates']) && is_array($node['coordinates']) ? $node['coordinates'] : null;
        }
        if ($type === 'feature') {
            $geom = $node['geometry'] ?? null;
            return is_array($geom) ? self::extractPolygon($geom) : null;
        }
        if ($type === 'featurecollection') {
            $features = $node['features'] ?? [];
            if (!is_array($features)) return null;
            foreach ($features as $feature) {
                if (!is_array($feature)) continue;
                $found = self::extractPolygon($feature);
                if ($found !== null) return $found;
            }
            return null;
        }
        // Bare coordinates array (Leaflet's toGeoJSON output sometimes arrives
        // unwrapped). A polygon looks like [[ [lng,lat], ... ]].
        if (isset($node[0]) && is_array($node[0]) && isset($node[0][0]) && is_array($node[0][0])) {
            return $node;
        }
        return null;
    }

    /** @return array<int,array{0:float,1:float}>|null */
    private static function cleanRing(array $ring): ?array
    {
        $out = [];
        foreach ($ring as $pos) {
            if (!is_array($pos) || count($pos) < 2) continue;
            $lng = $pos[0];
            $lat = $pos[1];
            if (!is_numeric($lng) || !is_numeric($lat)) {
                return null;
            }
            $lng = (float) $lng;
            $lat = (float) $lat;
            if (!is_finite($lng) || !is_finite($lat)) return null;
            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) return null;
            $point = [round($lng, 6), round($lat, 6)];
            if (!$out || end($out) !== $point) {
                $out[] = $point;
            }
        }
        if (count($out) < 3) return null;
        // Close the ring (GeoJSON requires first === last).
        if ($out[0] !== $out[count($out) - 1]) {
            $out[] = $out[0];
        }
        return count($out) >= 4 ? $out : null;
    }

    /** Spherical ring area in square metres. */
    private static function ringArea(array $ring): float
    {
        $n = count($ring);
        if ($n < 3) return 0.0;
        $earth = 6378137.0;
        $total = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];
            $total += deg2rad($b[0] - $a[0])
                * (2 + sin(deg2rad($a[1])) + sin(deg2rad($b[1])));
        }
        return abs($total * $earth * $earth / 2.0);
    }

    /** @return array{lat:float,lng:float}|null */
    private static function centroid(array $ring): ?array
    {
        $n = count($ring);
        if ($n === 0) return null;
        // Drop the repeated closing point for the average.
        if ($n > 1 && $ring[0] === $ring[$n - 1]) $n--;
        if ($n <= 0) return null;
        $lat = 0.0; $lng = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $lng += $ring[$i][0];
            $lat += $ring[$i][1];
        }
        return ['lat' => round($lat / $n, 6), 'lng' => round($lng / $n, 6)];
    }

    /** Human-readable area. */
    public static function formatHectares(?float $ha): string
    {
        return $ha === null ? '—' : number_format($ha, 2) . ' ha';
    }
}
