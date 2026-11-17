<?php
session_start();
if (!isset($_SESSION['login_toko'])) { header("Location: login.php"); exit; }
require_once 'koneksi.php';

$cari_kota = isset($_GET['kota']) ? mysqli_real_escape_string($conn, $_GET['kota']) : '';

$sql_pesanan = "SELECT * FROM view_transaksi_pelanggan";
if ($cari_kota !== '') {
    $sql_pesanan .= " WHERE kota = '$cari_kota'";
}
$sql_pesanan .= " ORDER BY id_transaksi DESC";
$q_pesanan = mysqli_query($conn, $sql_pesanan);

// Lihat detail transaksi tertentu jika diklik
$detail_items = [];
$id_pilih = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
if ($id_pilih > 0) {
    $q_det = mysqli_query($conn, "
        SELECT d.*, HitungTotal(d.harga, d.jumlah) AS subtotal
        FROM tbl_detail_transaksi d
        WHERE d.id_transaksi = $id_pilih
    ");
    $detail_items = mysqli_fetch_all($q_det, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Semua Pesanan - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h2 class="font-bold text-lg flex items-center gap-2">
                    <i data-lucide="clipboard-list" class="w-5 h-5 text-indigo-600"></i> Daftar Semua Pesanan Toko (`view_transaksi_pelanggan`)
                </h2>
                <p class="text-xs text-slate-500">Menampilkan seluruh transaksi masuk dari pelanggan beserta rincian itemnya</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="kota" value="<?= htmlspecialchars($cari_kota) ?>" placeholder="Filter Kota (mis: Ponorogo)" class="border p-2 rounded-xl text-xs">
                <button type="submit" class="bg-indigo-600 text-white px-3 py-2 rounded-xl text-xs font-semibold">Filter</button>
                <?php if ($cari_kota): ?><a href="pesanan.php" class="text-xs text-rose-600 hover:underline">Reset</a><?php endif; ?>
            </form>
        </div>

        <?php if ($id_pilih > 0): ?>
        <div class="bg-indigo-50 border border-indigo-200 p-5 rounded-2xl">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-sm text-indigo-900">Rincian Barang pada Pesanan #TRX-<?= $id_pilih ?> (`tbl_detail_transaksi`)</h3>
                <a href="pesanan.php" class="text-xs text-indigo-700 font-semibold hover:underline">Tutup Rincian ✕</a>
            </div>
            <?php if (empty($detail_items)): ?>
                <p class="text-xs text-slate-500">Transaksi ini merupakan data awal (tanpa rincian item produk spesifik).</p>
            <?php else: ?>
                <table class="w-full text-xs bg-white rounded-xl overflow-hidden">
                    <thead><tr class="bg-indigo-100 text-indigo-900 text-left"><th class="p-2.5">Nama Produk</th><th class="p-2.5">Harga</th><th class="p-2.5">Jumlah</th><th class="p-2.5 text-right">Subtotal (`HitungTotal`)</th></tr></thead>
                    <tbody>
                        <?php foreach ($detail_items as $di): ?>
                        <tr class="border-b"><td class="p-2.5 font-semibold"><?= $di['nama_produk'] ?></td><td class="p-2.5">Rp <?= number_format($di['harga'], 0, ',', '.') ?></td><td class="p-2.5"><?= $di['jumlah'] ?></td><td class="p-2.5 text-right font-bold">Rp <?= number_format($di['subtotal'], 0, ',', '.') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-left text-xs text-slate-600">
                        <th class="p-3">ID Pesanan</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Nama Pelanggan</th>
                        <th class="p-3">Kota</th>
                        <th class="p-3">Total Belanja</th>
                        <th class="p-3 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ps = mysqli_fetch_assoc($q_pesanan)): ?>
                    <tr class="border-b hover:bg-slate-50">
                        <td class="p-3 font-mono text-xs font-bold text-indigo-600">#TRX-<?= $ps['id_transaksi'] ?></td>
                        <td class="p-3"><?= $ps['tanggal'] ?></td>
                        <td class="p-3 font-semibold"><?= $ps['nama_pelanggan'] ?></td>
                        <td class="p-3"><?= $ps['kota'] ?></td>
                        <td class="p-3 font-bold text-emerald-700">Rp <?= number_format($ps['total_belanja'], 0, ',', '.') ?></td>
                        <td class="p-3 text-right">
                            <a href="?detail=<?= $ps['id_transaksi'] ?>" class="bg-slate-100 hover:bg-indigo-100 text-indigo-700 px-3 py-1 rounded-lg text-xs font-semibold">Lihat Item</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>