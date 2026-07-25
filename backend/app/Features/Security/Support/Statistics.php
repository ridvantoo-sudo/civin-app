<?php

namespace App\Features\Security\Support;

/**
 * Small statistics helpers shared by the detectors.
 *
 * Deliberately dependency-free: these run inside queue workers on every event,
 * so they must stay allocation-light.
 */
final class Statistics
{
    /**
     * @param  list<float>  $samples
     */
    public static function mean(array $samples): float
    {
        $count = count($samples);

        return $count === 0 ? 0.0 : array_sum($samples) / $count;
    }

    /**
     * Population standard deviation.
     *
     * @param  list<float>  $samples
     */
    public static function stdDev(array $samples): float
    {
        $count = count($samples);
        if ($count < 2) {
            return 0.0;
        }

        $mean = self::mean($samples);
        $variance = 0.0;
        foreach ($samples as $sample) {
            $variance += ($sample - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }

    /**
     * Coefficient of variation. Near-zero means the samples are suspiciously
     * regular — the core tell for auto-clickers and macros.
     *
     * @param  list<float>  $samples
     */
    public static function coefficientOfVariation(array $samples): float
    {
        $mean = self::mean($samples);
        if (abs($mean) < 1e-9) {
            return 0.0;
        }

        return self::stdDev($samples) / abs($mean);
    }

    /**
     * Chi-square goodness-of-fit against a uniform distribution. Used to spot
     * dice/slot outcomes that are too skewed to be honest RNG.
     *
     * @param  array<array-key, int>  $observed
     */
    public static function chiSquareUniform(array $observed): float
    {
        $total = array_sum($observed);
        $buckets = count($observed);
        if ($total <= 0 || $buckets < 2) {
            return 0.0;
        }

        $expected = $total / $buckets;
        $chi = 0.0;
        foreach ($observed as $count) {
            $chi += (($count - $expected) ** 2) / $expected;
        }

        return $chi;
    }

    /**
     * Critical chi-square value at p = 0.001 for the given degrees of freedom.
     * Table lookup keeps us free of a stats extension; values beyond the table
     * fall back to a Wilson–Hilferty approximation.
     */
    public static function chiSquareCritical(int $degreesOfFreedom): float
    {
        $table = [
            1 => 10.83, 2 => 13.82, 3 => 16.27, 4 => 18.47, 5 => 20.52,
            6 => 22.46, 7 => 24.32, 8 => 26.12, 9 => 27.88, 10 => 29.59,
            11 => 31.26, 12 => 32.91, 15 => 37.70, 20 => 45.31,
        ];

        if (isset($table[$degreesOfFreedom])) {
            return $table[$degreesOfFreedom];
        }

        if ($degreesOfFreedom < 1) {
            return 0.0;
        }

        $z = 3.09;
        $term = 1 - (2 / (9 * $degreesOfFreedom)) + $z * sqrt(2 / (9 * $degreesOfFreedom));

        return $degreesOfFreedom * ($term ** 3);
    }

    /**
     * Scales a ratio of actual-to-limit into a 0-100 confidence, saturating at
     * `ceiling` so a single extreme sample cannot produce runaway scores.
     */
    public static function confidenceFromRatio(float $actual, float $limit, float $ceiling = 95.0): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        $ratio = $actual / $limit;
        if ($ratio <= 1.0) {
            return 0.0;
        }

        return min($ceiling, 50.0 + (($ratio - 1.0) * 50.0));
    }

    /**
     * Inverse of the above: confidence grows as the value falls further below
     * the floor (used for "too fast" and "too regular" checks).
     */
    public static function confidenceBelowFloor(float $actual, float $floor, float $ceiling = 95.0): float
    {
        if ($floor <= 0 || $actual >= $floor) {
            return 0.0;
        }

        $shortfall = ($floor - $actual) / $floor;

        return min($ceiling, 50.0 + ($shortfall * 50.0));
    }
}
