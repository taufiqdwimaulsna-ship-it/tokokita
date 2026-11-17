<?php
session_start();
if (!isset($_SESSION['login_toko'])) { header("Location: login.php"); exit; }
require_once 'koneksi.php';

$id_pelanggan = isset($_GET['id_pelanggan']) ? (int)$_GET['id_pelanggan'] : 1;

// 1. Panggil Stored Procedure CekTransaksi(id_pelanggan) (Bab 3.E: IF ELSE)
$q_cek = mysqli_query($conn, "CALL CekTransaksi($id_pelanggan)");
$status_trx = mysqli_fetch_assoc($q_cek);
while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}

// 2. Panggil Stored Procedure LaporanTransaksi(id_pelanggan) (Bab 3.H.5)
$q_laporan = mysqli_query($conn, "CALL LaporanTransaksi($id_pelanggan)");
$riwayat_saya = mysqli_fetch_all($q_laporan, MYSQLI_ASSOC);
while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}

// 3. Panggil Stored Procedure CariPelanggan(id_pelanggan) (Bab 3.C)
$q_info = mysqli_query($conn, "CALL CariPelanggan($id_pelanggan)");
$info_pel = mysqli_fetch_assoc($q_info);
while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}

$q_semua_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesanan Saya - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h2 class="font-bold text-lg flex items-center gap-2">
                    <i data-lucide="user-check" class="w-5 h-5 text-indigo-600"></i>
                    Pesanan Saya: <?= htmlspecialchars($info_pel['nama_pelanggan'] ?? '-') ?> (<?= htmlspecialchars($info_pel['kota'] ?? '-') ?>)
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Status (`CALL CekTransaksi`): <strong class="text-emerald-600"><?= $status_trx['keterangan'] ?? '-' ?></strong>
                </p>
            </div>

            <form method="GET" class="flex items-center gap-2">
                <label class="text-xs font-bold text-slate-600">Pilih Pelanggan:</label>
                <select name="id_pelanggan" onchange="this.form.submit()" class="border p-2.5 rounded-xl text-xs font-semibold">
                    <?php while ($sp = mysqli_fetch_assoc($q_semua_pel)): ?>
                        <option value="<?= $sp['id_pelanggan'] ?>" <?= $sp['id_pelanggan'] == $id_pelanggan ? 'selected' : '' ?>>
                            #<?= $sp['id_pelanggan'] ?> - <?= $sp['nama_pelanggan'] ?> (<?= $sp['kota'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="font-bold text-base mb-4">Hasil Pemanggilan `CALL LaporanTransaksi(<?= $id_pelanggan ?>)`</h3>
            <?php if (empty($riwayat_saya)): ?>
                <div class="text-center py-12 text-slate-400">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 opacity-40"></i>
                    <p class="text-sm">Pelanggan ini belum memiliki riwayat pesanan.</p>
                </div>
            <?php else: ?>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-left text-xs text-slate-600">
                            <th class="p-3">No. Pesanan</th>
                            <th class="p-3">Tanggal Transaksi</th>
                            <th class="p-3">Nama Pelanggan</th>
                            <th class="p-3">Kota</th>
                            <th class="p-3 text-right">Total Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_semua = 0;
                        foreach ($riwayat_saya as $rs): 
                            $total_semua += $rs['total_belanja'];
                        ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 font-mono text-xs font-bold text-indigo-600">#TRX-<?= $rs['id_transaksi'] ?></td>
                            <td class="p-3"><?= $rs['tanggal'] ?></td>
                            <td class="p-3 font-semibold"><?= $rs['nama_pelanggan'] ?></td>
                            <td class="p-3"><?= $rs['kota'] ?></td>
                            <td class="p-3 text-right font-bold text-emerald-700">Rp <?= number_format($rs['total_belanja'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-slate-900 text-white font-bold">
                            <td colspan="4" class="p-3 text-right">Akumulasi Belanja Pelanggan Ini:</td>
                            <td class="p-3 text-right text-emerald-400">Rp <?= number_format($total_semua, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>