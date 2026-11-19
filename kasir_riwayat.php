<?php
require_once 'functions.php';
batasiAksesPelanggan();

$q_riwayat = mysqli_query($conn, "
    SELECT v.*, t.waktu_transaksi, HitungPajak(v.total_belanja) AS ppn, KategoriBelanja(v.total_belanja) AS level_pelanggan
    FROM view_transaksi_pelanggan v
    JOIN tbl_transaksi t ON t.id_transaksi = v.id_transaksi
    ORDER BY v.id_transaksi DESC
");

$stat_riwayat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_trx, COALESCE(SUM(total_belanja), 0) AS total_omzet, COALESCE(AVG(total_belanja), 0) AS avg_belanja
    FROM tbl_transaksi
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi Kasir - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Header Banner & Summary Stats -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                    <i data-lucide="history" class="w-5 h-5 text-indigo-600"></i> Riwayat Lengkap Transaksi Kasir
                </h1>
                <p class="text-xs text-slate-400 mt-0.5">Seluruh rekaman penjualan dari <code>view_transaksi_pelanggan</code> terintegrasi</p>
            </div>

            <!-- Mini stats pill -->
            <div class="flex flex-wrap items-center gap-3 text-xs">
                <div class="bg-indigo-50 border border-indigo-100 px-3 py-1.5 rounded-2xl">
                    <span class="text-indigo-600 font-bold">Total:</span>
                    <span class="font-extrabold text-slate-800 ml-1"><?= number_format($stat_riwayat['total_trx']) ?> Trx</span>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-2xl">
                    <span class="text-emerald-600 font-bold">Omzet:</span>
                    <span class="font-extrabold text-emerald-700 ml-1"><?= rupiah($stat_riwayat['total_omzet']) ?></span>
                </div>
                <div class="bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-2xl">
                    <span class="text-amber-600 font-bold">Rata-rata:</span>
                    <span class="font-extrabold text-slate-800 ml-1"><?= rupiah($stat_riwayat['avg_belanja']) ?></span>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat Transaksi -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariTrx" onkeyup="filterTable('cariTrx', 'tabelRiwayatKasir')" placeholder="Cari no. struk, nama pelanggan, kota..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                <div class="flex items-center gap-2">
                    <a href="kasir_pos.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Transaksi Baru
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="tabelRiwayatKasir" class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[10px] border-b border-slate-100">
                            <th class="p-3.5">No. Struk</th>
                            <th class="p-3.5">Tanggal & Jam</th>
                            <th class="p-3.5">Nama Pelanggan</th>
                            <th class="p-3.5">Kota</th>
                            <th class="p-3.5">Status Loyalitas</th>
                            <th class="p-3.5 text-right">Total Belanja</th>
                            <th class="p-3.5 text-right">PPN 11%</th>
                            <th class="p-3.5 text-center">Aksi Struk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($r = mysqli_fetch_assoc($q_riwayat)): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5 font-mono font-bold text-indigo-600">
                                #TRX-<?= str_pad($r['id_transaksi'], 4, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="p-3.5 text-slate-500">
                                <?= htmlspecialchars($r['tanggal'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="block text-[10px] text-slate-400"><?= !empty($r['waktu_transaksi']) ? date('H:i:s', strtotime($r['waktu_transaksi'])) : 'Jam belum tercatat' ?></span>
                            </td>
                            <td class="p-3.5 font-bold text-slate-800"><?= htmlspecialchars($r['nama_pelanggan']) ?></td>
                            <td class="p-3.5 text-slate-600"><?= htmlspecialchars($r['kota']) ?></td>
                            <td class="p-3.5">
                                <span class="bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded-full text-[10px]">
                                    <?= $r['level_pelanggan'] ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-right font-extrabold text-emerald-600">
                                <?= rupiah($r['total_belanja']) ?>
                            </td>
                            <td class="p-3.5 text-right text-slate-400">
                                <?= rupiah($r['ppn']) ?>
                            </td>
                            <td class="p-3.5 text-center">
                                <a href="struk.php?id=<?= $r['id_transaksi'] ?>" class="bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 px-3 py-1.5 rounded-xl font-bold text-[11px] inline-flex items-center gap-1.5 shadow-sm transition">
                                    <i data-lucide="printer" class="w-3.5 h-3.5"></i> Struk 58mm
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
