<?php
require __DIR__ . '/../pricing.php';
// Memastikan hasil perhitungan sesuai skenario pengujian.
function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$items = [
    'karpet' => ['quantity' => 10, 'rate' => 200000],
    'obras' => ['quantity' => 20, 'rate' => 15000],
    'pasang' => ['quantity' => 10, 'rate' => 20000]
];
$result = calculate_pricing($items, [
    'karpet' => ['type' => 'percent', 'value' => 10],
    'obras' => ['type' => 'amount', 'value' => 50000],
    'total' => ['type' => 'amount', 'value' => 250000]
]);
expect(
    $result['subtotal'] === 2250000.0 && $result['total'] === 2000000.0,
    'Diskon gabungan salah'
);
expect(
    calculate_pricing($items, [])['total'] === 2500000.0,
    'Tanpa diskon salah'
);
expect(
    calculate_pricing($items, ['total' => ['value' => 100]])['total'] === 0.0,
    'Diskon penuh salah'
);
expect(
    calculate_pricing($items, [
        'karpet' => ['value' => 10],
        'total' => ['value' => 10]
    ])['total'] === 2070000.0,
    'Urutan diskon salah'
);
expect(
    calculate_pricing($items, ['lem' => ['value' => 150]])['total'] ===
        2500000.0,
    'Item nonaktif ikut dihitung'
);
expect(
    calculate_pricing(['lem' => ['quantity' => 0.25, 'rate' => 12345]], [])[
        'total'
    ] === 3086.25,
    'Tarif atau volume pecahan salah'
);
foreach (
    [
        ['value' => -1],
        ['value' => 101],
        ['type' => 'amount', 'value' => 2500001],
        ['type' => 'invalid'],
        ['value' => 'abc'],
        ['value' => []],
        ['value' => INF]
    ]
    as $bad
) {
    try {
        calculate_pricing($items, ['total' => $bad]);
        throw new RuntimeException('Diskon tidak valid diterima');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "Semua pengujian harga dan diskon lulus.\n";
