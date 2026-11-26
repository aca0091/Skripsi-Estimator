<?php
require_once __DIR__ . '/pricing.php';

// Mengamankan teks penawaran sebelum ditampilkan dalam HTML.
function quotation_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Menyediakan token sesi untuk melindungi formulir penawaran.
function quotation_token()
{
    if (empty($_SESSION['quotation_csrf'])) {
        $_SESSION['quotation_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['quotation_csrf'];
}

// Menolak pengiriman formulir dengan token yang tidak sesuai.
function quotation_check_token()
{
    if (
        !is_string($_POST['csrf'] ?? null) ||
        !hash_equals(quotation_token(), $_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Form kedaluwarsa. Muat ulang halaman.');
    }
}

// Menjumlahkan subtotal estimasi dan menerapkan diskon penawaran sekali.
function quotation_pricing(array $rows, array $discount)
{
    if (!$rows) {
        throw new InvalidArgumentException('Tambahkan minimal satu estimasi.');
    }
    $items = [];
    foreach ($rows as $i => $row) {
        $items[$i] = ['quantity' => 1, 'rate' => $row['pricing']['subtotal']];
    }
    return calculate_pricing($items, ['total' => $discount]);
}

// Alokasikan diskon total ke tiap estimasi agar rekap laporan tetap sesuai penawaran.
function quotation_allocations(array $rows, array $pricing)
{
    $remaining = (int) round($pricing['discount']['amount'] * 100);
    $baseRemaining = (int) round($pricing['subtotal'] * 100);
    $result = [];
    foreach ($rows as $row) {
        $base = (int) round($row['pricing']['subtotal'] * 100);
        $share =
            $baseRemaining > 0
                ? (int) round(($remaining * $base) / $baseRemaining)
                : 0;
        $share = min($base, $remaining, $share);
        $result[] = ($base - $share) / 100;
        $remaining -= $share;
        $baseRemaining -= $base;
    }
    return $result;
}

// Menyimpan penawaran, ruangan, dan estimasi dalam satu transaksi.
function save_quotation(PDO $pdo, array $draft, array $discount)
{
    $pricing = quotation_pricing($draft['rows'], $discount);
    $totals = quotation_allocations($draft['rows'], $pricing);
    $pdo->beginTransaction();
    try {
        // Simpan induk penawaran sebelum membuat rincian tiap ruangan.
        $q = $pdo->prepare(
            'INSERT INTO tb_penawaran (
                id_customer, nama_pelanggan, tanggal_penawaran, pricing_snapshot, total_biaya
            ) VALUES (?, ?, ?, ?, ?)'
        );
        $q->execute([
            $draft['customer'],
            $draft['customer_name'],
            $draft['date'],
            json_encode($pricing, JSON_THROW_ON_ERROR),
            $pricing['total']
        ]);
        $id = (int) $pdo->lastInsertId();
        // Gunakan ID database agar setiap penawaran memiliki nomor unik.
        $code = 'PNW-' . str_pad($id, 5, '0', STR_PAD_LEFT);
        $pdo->prepare(
            'UPDATE tb_penawaran SET kode_penawaran=? WHERE id_penawaran=?'
        )->execute([$code, $id]);
        // Simpan harga dan gambar tiap estimasi untuk kebutuhan cetak.
        foreach (array_values($draft['rows']) as $i => $row) {
            $g = $row['geometry'];
            $pdo->prepare(
                'INSERT INTO tb_ruangan (
                    nama_ruangan, nama_pelanggan, panjang, lebar,
                    luas_objek, luas_kotor, luas_bersih
                ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $row['name'],
                $draft['customer_name'],
                $g['width'],
                $g['height'],
                $row['object_area'],
                $g['area'],
                $row['net_area']
            ]);
            $room = $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO tb_estimasi (
                    kode_estimasi, id_ruangan, id_karpet, id_user,
                    arah_pemasangan, metode_pemasangan, total_luas, total_meter_lari,
                    total_sambungan, total_obras, biaya_lem, biaya_jasa_pasang,
                    luas_waste, total_biaya, tanggal_estimasi, layout_geometry,
                    id_customer, pricing_snapshot, id_penawaran, layout_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $code . '/' . ($i + 1),
                $room,
                $row['carpet'],
                $_SESSION['user_id'],
                $row['direction'],
                $row['method'],
                $row['net_area'],
                $row['meters'],
                $row['seams'],
                $row['binding'],
                $row['pricing']['items']['lem']['gross'] ?? 0,
                $row['pricing']['items']['pasang']['gross'] ?? 0,
                $row['waste'],
                $totals[$i],
                $draft['date'],
                $g['json'],
                $draft['customer'],
                json_encode($row['pricing'], JSON_THROW_ON_ERROR),
                $id,
                $row['image']
            ]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        // Batalkan semua penyimpanan jika salah satu rincian gagal.
        $pdo->rollBack();
        throw $error;
    }
}
