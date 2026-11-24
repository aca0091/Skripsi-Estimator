<?php
// Validate and measure a simple polygon expressed in metres.
// Memvalidasi batas dan menghitung ukuran ruangan.
function measure_room_geometry($json)
{
    $geometry = json_decode($json, true);
    $points = $geometry['points'] ?? null;
    if (!is_array($points) || count($points) < 3 || count($points) > 100) {
        throw new InvalidArgumentException('Batas ruangan harus memiliki 3–100 titik.');
    }
    foreach ($points as $point) {
        if (!is_array($point) || count($point) !== 2) {
            throw new InvalidArgumentException('Titik ruangan tidak valid.');
        }
        foreach ($point as $value) {
            if (
                (!is_int($value) && !is_float($value)) ||
                !is_finite((float) $value) ||
                $value < 0 ||
                $value > 10000
            ) {
                throw new InvalidArgumentException('Koordinat ruangan tidak valid.');
            }
        }
    }
    $cross = static function ($a, $b, $c) {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
    };
    $on = static function ($a, $b, $c) use ($cross) {
        return abs($cross($a, $b, $c)) < 1e-9 &&
            $c[0] >= min($a[0], $b[0]) - 1e-9 &&
            $c[0] <= max($a[0], $b[0]) + 1e-9 &&
            $c[1] >= min($a[1], $b[1]) - 1e-9 &&
            $c[1] <= max($a[1], $b[1]) + 1e-9;
    };
    $area = 0;
    $perimeter = 0;
    $n = count($points);
    for ($i = 0; $i < $n; $i++) {
        $a = $points[$i];
        $b = $points[($i + 1) % $n];
        $length = hypot($b[0] - $a[0], $b[1] - $a[1]);
        if ($length < 1e-8) {
            throw new InvalidArgumentException('Ada titik sudut yang berimpit.');
        }
        $area += $a[0] * $b[1] - $b[0] * $a[1];
        $perimeter += $length;
        for ($j = $i + 1; $j < $n; $j++) {
            if ($j === $i + 1 || ($i === 0 && $j === $n - 1)) {
                continue;
            }
            $c = $points[$j];
            $d = $points[($j + 1) % $n];
            if (
                ($cross($a, $b, $c) * $cross($a, $b, $d) < 0 &&
                    $cross($c, $d, $a) * $cross($c, $d, $b) < 0) ||
                $on($a, $b, $c) ||
                $on($a, $b, $d) ||
                $on($c, $d, $a) ||
                $on($c, $d, $b)
            ) {
                throw new InvalidArgumentException('Batas ruangan saling bersilangan.');
            }
        }
    }
    $area = abs($area) / 2;
    if ($area < 1e-8) {
        throw new InvalidArgumentException('Luas ruangan harus lebih dari nol.');
    }
    return [
        'area' => $area,
        'perimeter' => $perimeter,
        'width' => max(array_column($points, 0)) - min(array_column($points, 0)),
        'height' => max(array_column($points, 1)) - min(array_column($points, 1)),
        'json' => json_encode(['version' => 1, 'points' => $points]),
    ];
}
