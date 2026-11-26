<?php
// Memvalidasi angka biaya dan volume sebelum digunakan dalam perhitungan.
function pricing_number($value, $label)
{
    if (
        !is_scalar($value) ||
        !is_numeric($value) ||
        !is_finite((float) $value) ||
        $value < 0 ||
        $value > 1000000000000
    ) {
        throw new InvalidArgumentException(
            "$label harus berupa angka positif atau nol yang valid."
        );
    }
    return (float) $value;
}

// Menghitung potongan persen atau nominal, maksimal sebesar subtotal.
function pricing_discount($base, $input)
{
    if (!is_array($input)) {
        throw new InvalidArgumentException('Format diskon tidak valid.');
    }
    $type = $input['type'] ?? 'percent';
    if (!in_array($type, ['percent', 'amount'], true)) {
        throw new InvalidArgumentException('Jenis diskon tidak valid.');
    }
    $value = pricing_number(
        ($input['value'] ?? '') === '' ? 0 : $input['value'],
        'Diskon'
    );
    if (
        ($type === 'percent' && $value > 100) ||
        ($type === 'amount' && $value > $base)
    ) {
        throw new InvalidArgumentException(
            'Diskon maksimal 100% atau sebesar subtotal yang dikenai diskon.'
        );
    }
    return [
        'type' => $type,
        'value' => $value,
        'amount' => round(
            $type === 'percent' ? ($base * $value) / 100 : $value,
            2
        )
    ];
}

// Menghitung diskon item terlebih dahulu, kemudian diskon keseluruhan.
function calculate_pricing(array $items, array $discounts)
{
    $gross = 0;
    $subtotal = 0;
    foreach ($items as $key => &$item) {
        $item['quantity'] = pricing_number($item['quantity'], 'Volume');
        $item['rate'] = pricing_number($item['rate'], 'Tarif');
        // Pembulatan dua desimal menjaga nilai uang konsisten saat disimpan.
        $item['gross'] = round($item['quantity'] * $item['rate'], 2);
        pricing_number($item['gross'], 'Subtotal');
        $item['discount'] = pricing_discount(
            $item['gross'],
            $discounts[$key] ?? []
        );
        $item['net'] = round($item['gross'] - $item['discount']['amount'], 2);
        $gross += $item['gross'];
        $subtotal += $item['net'];
    }
    unset($item);
    $gross = round($gross, 2);
    $subtotal = round($subtotal, 2);
    $discount = pricing_discount($subtotal, $discounts['total'] ?? []);
    return [
        'items' => $items,
        'gross' => $gross,
        'item_discount' => round($gross - $subtotal, 2),
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => round($subtotal - $discount['amount'], 2)
    ];
}
