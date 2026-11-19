<?php
require_once 'functions.php';
batasiAksesPelanggan();

$q_antrean = mysqli_query($conn, "
    SELECT t.*, p.nama_pelanggan, p.kota,
           (SELECT COUNT(*) FROM tbl_detail_transaksi d WHERE d.id_transaksi = t.id_transaksi) AS jml_item
    FROM tbl_transaksi t
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC LIMIT 12
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kasir Pesanan - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-center">
            <div>
                <h1 class="text-lg font-bold flex items-center gap-2">
                    <i data-lucide="clock" class="w-5 h-5 text-amber-500"></i> Antrean Pesanan Kasir (`kasir_pesanan.php`)
                </h1>
                <p class="text-xs text-slate-500">Daftar pesanan pelanggan terbaru beserta status pembayaran</p>
            </div>
            <a href="kasir_pos.php" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-xs font-bold">+ Input Pesanan Baru</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <?php while ($a = mysqli_fetch_assoc($q_antrean)): ?>
            <?php
                $status_pembayaran = strtolower(trim((string)($a['status_pembayaran'] ?? '')));
                if (in_array($status_pembayaran, ['pending', 'menunggu pembayaran'], true)) {
                    $label_status = 'Menunggu Pembayaran';
                    $kelas_status = 'bg-amber-100 text-amber-800';
                } elseif (in_array($status_pembayaran, ['gagal', 'batal', 'dibatalkan', 'cancelled', 'canceled', 'expired', 'kadaluarsa'], true)) {
                    $label_status = 'Transaksi Batal';
                    $kelas_status = 'bg-rose-100 text-rose-800';
                } elseif (in_array($status_pembayaran, ['lunas', 'berhasil'], true)) {
                    $label_status = 'Selesai Dibayar';
                    $kelas_status = 'bg-emerald-100 text-emerald-800';
                } else {
                    $label_status = $a['status_pembayaran'] ?: 'Status Belum Tersedia';
                    $kelas_status = 'bg-slate-100 text-slate-700';
                }
            ?>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="font-mono text-xs font-bold text-indigo-600">#TRX-<?= $a['id_transaksi'] ?></span>
                    <span class="<?= $kelas_status ?> px-2.5 py-0.5 rounded-full text-[11px] font-bold"><?= htmlspecialchars($label_status, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div>
                    <h3 class="font-bold text-base"><?= $a['nama_pelanggan'] ?></h3>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($a['kota'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($a['tanggal'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($a['waktu_transaksi']) ? date('H:i:s', strtotime($a['waktu_transaksi'])) : 'Jam belum tercatat' ?></p>
                    <p class="text-lg font-extrabold text-slate-800 mt-2"><?= rupiah($a['total_belanja']) ?></p>
                </div>
                <div class="flex justify-between items-center pt-2 border-t text-xs">
                    <span class="text-slate-500"><?= $a['jml_item'] ?> Rincian Produk</span>
                    <a href="struk.php?id=<?= $a['id_transaksi'] ?>" class="text-indigo-600 font-bold hover:underline flex items-center gap-1">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Struk →
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>