<?php
require __DIR__ . '/../room_geometry.php';
$shape = ['points' => [[0, 0], [4, 0], [10, 2], [10, 4], [0, 4]]];
$measured = measure_room_geometry(json_encode($shape));
if (
    abs($measured['area'] - 34) > 1e-8 ||
    abs($measured['perimeter'] - (20 + sqrt(40))) > 1e-8
) {
    throw new RuntimeException('Ukuran poligon salah.');
}
foreach (
    ['{}', '{"points":[[0,0],[4,4],[0,4],[4,0]]}', '{"points":[[0,0],[4,0],[4,0],[0,4]]}']
    as $invalid
) {
    try {
        measure_room_geometry($invalid);
    } catch (InvalidArgumentException $expected) {
        continue;
    }
    throw new RuntimeException('Batas tidak valid diterima.');
}
echo "Validasi dan pengukuran kontur server berhasil.\n";
