<?php
require_once 'functions.php';
cekLogin();

$id_produk = isset($_GET['id']) ? (int)$_GET['id'] : 1;

$q_prod = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori,
           HitungPajak(p.harga) AS ppn,
           HitungDiskon(p.harga, 10) AS pot_10,
           HitungTotal(p.harga, 5) AS harga_grosir_5
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
    WHERE p.id_produk = $id_produk
");
$prod = mysqli_fetch_assoc($q_prod);

// Riwayat penjualan produk ini
$q_terjual = mysqli_query($conn, "
    SELECT d.*, t.tanggal, p.nama_pelanggan
    FROM tbl_detail_transaksi d
    JOIN tbl_transaksi t ON d.id_transaksi = t.id_transaksi
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE d.id_produk = $id_produk
    ORDER BY d.id_detail DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Produk - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-5xl mx-auto p-6 space-y-6">
        <?php if (!$prod): ?>
            <div class="bg-white p-8 rounded-2xl text-center">Produk tidak ditemukan. <a href="admin_produk.php" class="text-indigo-600 underline">Kembali</a></div>
        <?php else: ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-indigo-50 rounded-2xl p-5 flex flex-col items-center justify-center gap-3">
                    <img src="<?= htmlspecialchars(getFotoProduk($prod['nama_produk'], $prod['foto_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($prod['nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                        class="max-h-64 w-full rounded-xl object-contain" loading="lazy"
                        onerror="this.onerror=null;this.src='<?= htmlspecialchars(getFotoProduk(''), ENT_QUOTES, 'UTF-8') ?>'">
                    <span class="bg-indigo-600 text-white text-xs px-3 py-1 rounded-full font-bold"><?= $prod['nama_kategori'] ?? 'Umum' ?></span>
                </div>
                <div class="md:col-span-2 space-y-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-extrabold"><?= $prod['nama_produk'] ?></h1>
                            <p class="text-xs text-slate-400">Kode SKU: #PRD-0<?= $prod['id_produk'] ?></p>
                        </div>
                        <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-xs font-bold">Stok: <?= $prod['stok'] ?> Unit</span>
                    </div>
                    <p class="text-3xl font-extrabold text-indigo-700"><?= rupiah($prod['harga']) ?></p>

                    <div class="grid grid-cols-3 gap-3 pt-3 border-t text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl border">
                            <span class="text-slate-400 block">PPN 11% (`HitungPajak`)</span>
                            <strong class="text-sm"><?= rupiah($prod['ppn']) ?></strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border">
                            <span class="text-slate-400 block">Diskon 10% (`HitungDiskon`)</span>
                            <strong class="text-sm text-amber-600">-<?= rupiah($prod['pot_10']) ?></strong>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border">
                            <span class="text-slate-400 block">Paket 5 Pcs (`HitungTotal`)</span>
                            <strong class="text-sm text-emerald-700"><?= rupiah($prod['harga_grosir_5']) ?></strong>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="kasir_pos.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold">Beli di Kasir POS</a>
                        <a href="admin_produk.php?fav=<?= $prod['id_produk'] ?>" class="border border-rose-300 text-rose-600 hover:bg-rose-50 px-4 py-2.5 rounded-xl text-xs font-bold">♥ Tambah ke Favorit</a>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="font-bold text-base mb-3">Riwayat Pembelian Produk Ini (`JOIN tbl_detail_transaksi`)</h2>
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-slate-100 text-left text-xs"><th class="p-2.5">No. Transaksi</th><th class="p-2.5">Tanggal</th><th class="p-2.5">Pembeli</th><th class="p-2.5">Jumlah</th></tr></thead>
                    <tbody>
                        <?php while ($t = mysqli_fetch_assoc($q_terjual)): ?>
                        <tr class="border-b"><td class="p-2.5 font-mono text-xs">#TRX-<?= $t['id_transaksi'] ?></td><td class="p-2.5"><?= $t['tanggal'] ?></td><td class="p-2.5 font-semibold"><?= $t['nama_pelanggan'] ?></td><td class="p-2.5 font-bold"><?= $t['jumlah'] ?> unit</td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>