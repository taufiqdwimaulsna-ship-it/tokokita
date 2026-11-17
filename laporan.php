<?php
require_once 'functions.php';
batasiAksesPelanggan();


// 1. Multiple JOIN 3 Tabel (Bab 2.C: tbl_pelanggan + tbl_transaksi + tbl_detail_transaksi)
$q_multiple_join = mysqli_query($conn, "
    SELECT p.nama_pelanggan, p.kota, t.id_transaksi, t.tanggal, d.nama_produk, d.jumlah, d.harga,
           HitungTotal(d.harga, d.jumlah) AS subtotal_item
    FROM tbl_pelanggan p
    INNER JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
    INNER JOIN tbl_detail_transaksi d ON t.id_transaksi = d.id_transaksi
    ORDER BY t.id_transaksi DESC
");

// 2. Subquery Pelanggan yang Belum Pernah Bertransaksi (Bab 2.H.2)
$q_belum_trx = mysqli_query($conn, "
    SELECT * FROM tbl_pelanggan
    WHERE id_pelanggan NOT IN (SELECT DISTINCT id_pelanggan FROM tbl_transaksi)
");

// 3. Lima Transaksi dengan Nilai Terbesar (Bab 2.H.6)
$q_top5 = mysqli_query($conn, "
    SELECT t.id_transaksi, t.tanggal, p.nama_pelanggan, p.kota, t.total_belanja
    FROM tbl_transaksi t
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.total_belanja DESC LIMIT 5
");

// 4. Gabungan Kota Pelanggan & Cabang dengan UNION (Bab 2.G)
$q_union = mysqli_query($conn, "
    SELECT kota, 'Kota Pelanggan / Cabang (UNION)' AS sumber FROM tbl_pelanggan
    UNION
    SELECT kota, 'Kota Pelanggan / Cabang (UNION)' AS sumber FROM tbl_cabang
");

// 5. Analisis EXPLAIN Query (Bab 6.D.8)
$q_explain = mysqli_query($conn, "EXPLAIN SELECT * FROM tbl_pelanggan WHERE kota = 'Ponorogo'");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Laporan SQL Lanjutan - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    
    <!-- Navbar Utama Sistem -->
    <div class="no-print">
        <?php include 'navbar.php'; ?>
    </div>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Header Laporan -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                    <i data-lucide="file-bar-chart" class="w-5 h-5 text-indigo-600"></i> Pusat Laporan Eksekutif SQL Lanjutan
                </h1>
                <p class="text-xs text-slate-400 mt-0.5">Analisis Query Database Relasional: Multiple JOIN, Subquery, Klausa UNION, dan Analisis EXPLAIN</p>
            </div>
            <div class="no-print flex items-center gap-2.5">
                <a href="struk.php" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                    <i data-lucide="receipt" class="w-4 h-4"></i> Buka Struk Kasir 58mm
                </a>
                <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                    <i data-lucide="printer" class="w-4 h-4"></i> Cetak Laporan A4
                </button>
            </div>
        </div>

        <!-- 1. Laporan Multiple JOIN 3 Tabel -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-2">
                    <i data-lucide="git-merge" class="w-4 h-4 text-indigo-600"></i>
                    1. Rincian Penjualan Barang (Multiple JOIN: <code>tbl_pelanggan</code> + <code>tbl_transaksi</code> + <code>tbl_detail_transaksi</code>)
                </h2>
                <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 rounded-lg">Bab 2.C</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[10px] border-b">
                            <th class="p-3">No. Transaksi</th>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Nama Pelanggan</th>
                            <th class="p-3">Produk Dibeli</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">Harga Satuan</th>
                            <th class="p-3 text-right">Subtotal</th>
                            <th class="p-3 text-center no-print">Aksi Struk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($mj = mysqli_fetch_assoc($q_multiple_join)): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3 font-mono font-bold text-indigo-600">#TRX-<?= str_pad($mj['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="p-3 text-slate-500"><?= $mj['tanggal'] ?></td>
                            <td class="p-3 font-bold text-slate-800"><?= htmlspecialchars($mj['nama_pelanggan']) ?> <span class="text-[10px] text-slate-400 font-normal">(<?= htmlspecialchars($mj['kota']) ?>)</span></td>
                            <td class="p-3 text-slate-700"><?= htmlspecialchars($mj['nama_produk']) ?></td>
                            <td class="p-3 text-center font-bold"><?= $mj['jumlah'] ?></td>
                            <td class="p-3 text-right text-slate-600">Rp <?= number_format($mj['harga'], 0, ',', '.') ?></td>
                            <td class="p-3 text-right font-black text-emerald-600">Rp <?= number_format($mj['subtotal_item'], 0, ',', '.') ?></td>
                            <td class="p-3 text-center no-print">
                                <a href="struk.php?id=<?= $mj['id_transaksi'] ?>" class="bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 px-2.5 py-1 rounded-lg font-bold text-[10px] transition inline-flex items-center gap-1">
                                    <i data-lucide="printer" class="w-3 h-3"></i> Struk 58mm
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grid 3 Kolom: Top 5 Transaksi, Subquery Belum Trx, UNION -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Top 5 Transaksi -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                        <i data-lucide="trending-up" class="w-4 h-4"></i> 2. Top 5 Transaksi Terbesar
                    </h3>
                    <span class="text-[10px] text-slate-400 font-mono">ORDER BY DESC</span>
                </div>
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                            <th class="p-2">Pelanggan</th>
                            <th class="p-2">Tanggal</th>
                            <th class="p-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($t5 = mysqli_fetch_assoc($q_top5)): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-2 font-bold text-slate-800"><?= htmlspecialchars($t5['nama_pelanggan']) ?></td>
                            <td class="p-2 text-slate-400 text-[11px]"><?= $t5['tanggal'] ?></td>
                            <td class="p-2 text-right font-black text-emerald-600">Rp <?= number_format($t5['total_belanja'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Subquery Pelanggan Belum Pernah Belanja -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-amber-700 flex items-center gap-1.5">
                        <i data-lucide="user-x" class="w-4 h-4"></i> 3. Member Belum Transaksi
                    </h3>
                    <span class="text-[10px] text-slate-400 font-mono">NOT IN (Subquery)</span>
                </div>
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                            <th class="p-2">ID</th>
                            <th class="p-2">Nama</th>
                            <th class="p-2 text-right">Kota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php 
                        if (mysqli_num_rows($q_belum_trx) === 0): ?>
                            <tr><td colspan="3" class="p-4 text-center text-slate-400">Semua member telah bertransaksi.</td></tr>
                        <?php else:
                            while ($bt = mysqli_fetch_assoc($q_belum_trx)): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-2 font-mono text-indigo-600">#<?= $bt['id_pelanggan'] ?></td>
                                <td class="p-2 font-bold text-slate-700"><?= htmlspecialchars($bt['nama_pelanggan']) ?></td>
                                <td class="p-2 text-right text-slate-500"><?= htmlspecialchars($bt['kota']) ?></td>
                            </tr>
                            <?php endwhile;
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- UNION Kota Pelanggan & Cabang -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h3 class="font-bold text-xs uppercase tracking-wider text-purple-700 flex items-center gap-1.5">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> 4. Distribusi Kota (UNION)
                    </h3>
                    <span class="text-[10px] text-slate-400 font-mono">Klausa UNION</span>
                </div>
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase border-b">
                            <th class="p-2">Kota Terdistribusi</th>
                            <th class="p-2 text-right">Sumber Relasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($un = mysqli_fetch_assoc($q_union)): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-2 font-bold text-slate-800 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                <?= htmlspecialchars($un['kota']) ?>
                            </td>
                            <td class="p-2 text-right text-[10px] text-slate-400">Pelanggan & Cabang</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- 5. Analisis EXPLAIN Query -->
        <div class="bg-slate-900 text-white p-6 rounded-3xl border border-slate-800 shadow-xl space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                <h3 class="font-bold text-xs uppercase tracking-wider text-indigo-400 flex items-center gap-2">
                    <i data-lucide="cpu" class="w-4 h-4"></i> 5. Analisis Optimasi Eksekusi Query (`EXPLAIN SELECT * FROM tbl_pelanggan WHERE kota = 'Ponorogo'`)
                </h3>
                <span class="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded-md font-mono">Query Cost Analysis</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left font-mono border-collapse">
                    <thead>
                        <tr class="text-slate-400 text-[10px] border-b border-slate-800">
                            <th class="p-2">id</th>
                            <th class="p-2">select_type</th>
                            <th class="p-2">table</th>
                            <th class="p-2">type</th>
                            <th class="p-2">possible_keys</th>
                            <th class="p-2">key</th>
                            <th class="p-2">rows</th>
                            <th class="p-2 text-right">Extra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php while ($ex = mysqli_fetch_assoc($q_explain)): ?>
                        <tr>
                            <td class="p-2 text-indigo-400 font-bold"><?= $ex['id'] ?></td>
                            <td class="p-2"><?= $ex['select_type'] ?></td>
                            <td class="p-2 text-emerald-400 font-bold"><?= $ex['table'] ?></td>
                            <td class="p-2"><?= $ex['type'] ?></td>
                            <td class="p-2 text-amber-400"><?= $ex['possible_keys'] ?? 'NULL' ?></td>
                            <td class="p-2 text-indigo-300 font-bold"><?= $ex['key'] ?? 'NULL' ?></td>
                            <td class="p-2 font-bold"><?= $ex['rows'] ?></td>
                            <td class="p-2 text-right text-slate-400"><?= $ex['Extra'] ?? 'Using where' ?></td>
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
