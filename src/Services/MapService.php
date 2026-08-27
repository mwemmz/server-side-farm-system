<?php
class MapService {
    /**
     * Build a Leaflet + OpenStreetMap embed config (no API key required).
     * @param float $lat
     * @param float $lon
     * @param string $label
     * @param int $zoom
     * @return array
     */
    public function getLeafletConfig($lat, $lon, $label = 'Farm', $zoom = 14) {
        return [
            'center'   => [$lat, $lon],
            'zoom'     => $zoom,
            'tileUrl'  => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'markers'  => [['lat' => $lat, 'lng' => $lon, 'label' => $label]],
        ];
    }

    /** Static map embed via OpenStreetMap static map service. */
    public function getStaticMapUrl($lat, $lon, $zoom = 15, $w = 600, $h = 300) {
        $markers = "pin-s+2ecc71({$lon},{$lat})";
        return "https://staticmap.openstreetmap.de/staticmap.php?center={$lat},{$lon}&zoom={$zoom}&size={$w}x{$h}&markers={$markers}";
    }
}
?>