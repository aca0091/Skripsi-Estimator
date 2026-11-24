<section class="customer-card">
    <h2>Rekap Customer</h2>
    <div class="customer-metrics">
        <div><strong><?= $customerReport['total'] ?></strong>Total Customer</div>
        <div><strong><?= $customerReport['new'] ?></strong>Customer Baru</div>
        <div><strong><?= $customerReport['active'] ?></strong>Customer Berproyek</div>
        <div><strong><?= $customerReport['repeat'] ?></strong>Repeat Customer</div>
    </div>
    <p class="customer-note">Total customer: terdaftar atau memiliki proyek sampai akhir
        periode. Customer baru: terdaftar pada periode terpilih. Customer berproyek dan
        repeat mengikuti filter proyek. Repeat: memiliki lebih dari satu proyek sampai
        akhir periode. Semua status proyek dihitung, termasuk Dibatalkan. Tanggal daftar
        customer hasil migrasi mengikuti proyek paling awal yang tercatat.</p>
    <div class="customer-table-wrap">
        <table class="customer-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Proyek dalam Filter</th>
                    <th>Proyek s.d. Akhir Periode</th>
                    <th>Nilai dalam Filter</th>
                    <th>Kategori</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customerReport['rows'] as $c): ?><tr>
                    <td><?= customer_escape( $c['nama_customer'] ) ?> (#<?= (int) $c['id_customer'] ?>)</td>
                    <td><?= (int) $c[ 'jumlah_periode' ] ?></td>
                    <td><?= (int) $c['jumlah_total'] ?></td>
                    <td>Rp <?= number_format( $c['nilai_periode'], 0, ',', '.' ) ?></td>
                    <td><?= $c['jumlah_total'] > 1
                    ? 'Repeat Customer'
                    : ($c['jumlah_total'] == 1
                        ? 'Satu Proyek'
                        : 'Belum Ada Proyek') ?></td>
                </tr><?php endforeach; ?>
                <?php if ( !$customerReport['rows'] ): ?><tr>
                    <td colspan="5">Belum ada customer sampai akhir periode ini.</td>
                </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
