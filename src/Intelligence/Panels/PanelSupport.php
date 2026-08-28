<?php

/**
 * Shared helpers for control-panel aggregation endpoints.
 * Provides threshold colouring so every gauge shows a green/amber/red band
 * rather than just a number.
 */
class PanelSupport {

    /**
     * Danger-band colour for a risk metric (higher = worse).
     * @param float $fraction 0..1 (e.g. spoilage risk)
     * @return string 'green'|'amber'|'red'
     */
    public static function riskColor($fraction) {
        if ($fraction < 0.5) return 'green';
        if ($fraction < 0.75) return 'amber';
        return 'red';
    }

    /**
     * Health-band colour for a level metric (higher = better).
     * @param float $fraction 0..1 (e.g. reservoir / capacity)
     * @return string 'green'|'amber'|'red'
     */
    public static function levelColor($fraction) {
        if ($fraction > 0.4) return 'green';
        if ($fraction > 0.2) return 'amber';
        return 'red';
    }

    /**
     * Warming-band for temperature/humidity ranges:
     * within safe band -> green; warning -> amber; outside -> red.
     */
    public static function bandColor($value, $safeMin, $safeMax, $warnMin, $warnMax) {
        if ($value >= $safeMin && $value <= $safeMax) return 'green';
        if ($value >= $warnMin && $value <= $warnMax) return 'amber';
        return 'red';
    }
}
