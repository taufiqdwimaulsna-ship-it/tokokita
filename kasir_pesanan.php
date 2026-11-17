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
                <p class="text-xs text-slate-500">Daftar pesanan pelanggan terbaru beserta status pemrosesan kasir</p>
            </div>
            <a href="kasir_pos.php" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-xs font-bold">+ Input Pesanan Baru</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <?php while ($a = mysqli_fetch_assoc($q_antrean)): ?>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between space-y-3">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="font-mono text-xs font-bold text-indigo-600">#TRX-<?= $a['id_transaksi'] ?></span>
                    <span class="bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full text-[11px] font-bold">Selesai Dibayar</span>
                </div>
                <div>
                    <h3 class="font-bold text-base"><?= $a['nama_pelanggan'] ?></h3>
                    <p class="text-xs text-slate-400"><?= $a['kota'] ?> • Tanggal: <?= $a['tanggal'] ?></p>
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