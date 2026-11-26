<?php
require __DIR__ . '/../quotation_helpers.php';
// Memastikan total gabungan dan alokasi diskon tetap konsisten.
function check_quote($ok, $message)
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$rows = [
    [
        'pricing' => calculate_pricing(
            ['karpet' => ['quantity' => 10, 'rate' => 200000]],
            ['karpet' => ['value' => 10]]
        )
    ],
    [
        'pricing' => calculate_pricing(
            [
                'karpet' => ['quantity' => 2, 'rate' => 150000],
                'obras' => ['quantity' => 4, 'rate' => 25000]
            ],
            ['obras' => ['type' => 'amount', 'value' => 50000]]
        )
    ]
];
$summary = quotation_pricing($rows, ['type' => 'percent', 'value' => 10]);
check_quote($summary['subtotal'] === 2150000.0, 'Subtotal gabungan salah');
check_quote(
    $summary['total'] === 1935000.0,
    'Diskon total tidak diterapkan sekali'
);
check_quote(
    abs(array_sum(quotation_allocations($rows, $summary)) - $summary['total']) <
        0.001,
    'Jumlah rekap berbeda dari penawaran'
);
foreach ([0, 0.01, 99.99, 100] as $value) {
    $summary = quotation_pricing($rows, ['value' => $value]);
    check_quote(
        abs(
            array_sum(quotation_allocations($rows, $summary)) -
                $summary['total']
        ) < 0.001,
        'Alokasi pecahan salah'
    );
}
$zero = [['pricing' => ['subtotal' => 0]], ['pricing' => ['subtotal' => 0]]];
check_quote(
    array_sum(quotation_allocations($zero, quotation_pricing($zero, []))) == 0,
    'Subtotal nol salah'
);
try {
    quotation_pricing([], []);
    throw new RuntimeException('Penawaran kosong diterima');
} catch (InvalidArgumentException $e) {
}
try {
    quotation_pricing($rows, ['type' => 'amount', 'value' => 3000000]);
    throw new RuntimeException('Diskon melebihi subtotal diterima');
} catch (InvalidArgumentException $e) {
}
echo "Pengujian gabungan estimasi, diskon, dan alokasi laporan lulus.\n";
