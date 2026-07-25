<?php

namespace App\Features\GeoDiscovery\Services;

/**
 * Base32 GeoHash encoder / neighbour helper (precision 1–12).
 */
final class GeoHashService
{
    private const string BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    /** Approximate cell width (km) at equator for each precision. */
    private const array CELL_KM = [
        1 => 5000,
        2 => 1250,
        3 => 156,
        4 => 39,
        5 => 4.9,
        6 => 1.2,
        7 => 0.15,
        8 => 0.038,
    ];

    public function encode(float $latitude, float $longitude, int $precision = 6): string
    {
        $precision = max(1, min(12, $precision));
        $latMin = -90.0;
        $latMax = 90.0;
        $lonMin = -180.0;
        $lonMax = 180.0;
        $hash = '';
        $bit = 0;
        $ch = 0;
        $even = true;

        while (strlen($hash) < $precision) {
            if ($even) {
                $mid = ($lonMin + $lonMax) / 2;
                if ($longitude >= $mid) {
                    $ch |= 1 << (4 - $bit);
                    $lonMin = $mid;
                } else {
                    $lonMax = $mid;
                }
            } else {
                $mid = ($latMin + $latMax) / 2;
                if ($latitude >= $mid) {
                    $ch |= 1 << (4 - $bit);
                    $latMin = $mid;
                } else {
                    $latMax = $mid;
                }
            }

            $even = ! $even;
            if ($bit < 4) {
                $bit++;
            } else {
                $hash .= self::BASE32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }

        return $hash;
    }

    /**
     * @return list<string> self + 8 neighbours at the given precision
     */
    public function neighbours(string $geohash): array
    {
        $hash = strtolower($geohash);
        if ($hash === '') {
            return [];
        }

        $decoded = $this->decodeBounds($hash);
        $lat = ($decoded['lat_min'] + $decoded['lat_max']) / 2;
        $lon = ($decoded['lon_min'] + $decoded['lon_max']) / 2;
        $dLat = ($decoded['lat_max'] - $decoded['lat_min']);
        $dLon = ($decoded['lon_max'] - $decoded['lon_min']);
        $precision = strlen($hash);

        $points = [
            [$lat, $lon],
            [$lat + $dLat, $lon],
            [$lat - $dLat, $lon],
            [$lat, $lon + $dLon],
            [$lat, $lon - $dLon],
            [$lat + $dLat, $lon + $dLon],
            [$lat + $dLat, $lon - $dLon],
            [$lat - $dLat, $lon + $dLon],
            [$lat - $dLat, $lon - $dLon],
        ];

        $hashes = [];
        foreach ($points as [$pLat, $pLon]) {
            $wrappedLon = fmod($pLon + 180.0, 360.0);
            if ($wrappedLon < 0) {
                $wrappedLon += 360.0;
            }
            $wrappedLon -= 180.0;
            $clampedLat = max(-90.0, min(90.0, $pLat));
            $hashes[] = $this->encode($clampedLat, $wrappedLon, $precision);
        }

        return array_values(array_unique($hashes));
    }

    /**
     * Choose a geohash prefix length that roughly covers the search radius.
     */
    public function precisionForRadiusKm(float $radiusKm): int
    {
        foreach ([8, 7, 6, 5, 4, 3, 2, 1] as $precision) {
            if ((self::CELL_KM[$precision] ?? 5000) * 2 >= $radiusKm) {
                return $precision;
            }
        }

        return 1;
    }

    /**
     * @return array{lat_min: float, lat_max: float, lon_min: float, lon_max: float}
     */
    public function decodeBounds(string $geohash): array
    {
        $hash = strtolower($geohash);
        $latMin = -90.0;
        $latMax = 90.0;
        $lonMin = -180.0;
        $lonMax = 180.0;
        $even = true;

        foreach (str_split($hash) as $char) {
            $idx = strpos(self::BASE32, $char);
            if ($idx === false) {
                break;
            }
            for ($mask = 16; $mask >= 1; $mask >>= 1) {
                if ($even) {
                    $mid = ($lonMin + $lonMax) / 2;
                    if (($idx & $mask) !== 0) {
                        $lonMin = $mid;
                    } else {
                        $lonMax = $mid;
                    }
                } else {
                    $mid = ($latMin + $latMax) / 2;
                    if (($idx & $mask) !== 0) {
                        $latMin = $mid;
                    } else {
                        $latMax = $mid;
                    }
                }
                $even = ! $even;
            }
        }

        return [
            'lat_min' => $latMin,
            'lat_max' => $latMax,
            'lon_min' => $lonMin,
            'lon_max' => $lonMax,
        ];
    }
}
