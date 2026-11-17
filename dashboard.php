<?php
require_once 'functions.php';
batasiAksesPelanggan();

$stat_omzet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_belanja),0) AS omzet, COUNT(*) AS jml_trx FROM tbl_transaksi"));
$stat_pel   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM tbl_pelanggan"));
$stat_prod  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS item, COALESCE(SUM(stok),0) AS stok FROM tbl_produk"));
$stat_topup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(nominal),0) AS total FROM tbl_topup"));

$q_rekap = mysqli_query($conn, "
    SELECT id_pelanggan, nama_pelanggan, total_transaksi, total_belanja,
           KategoriBelanja(total_belanja) AS kategori
    FROM view_total_belanja
    ORDER BY total_belanja DESC LIMIT 6
");

$q_recent_trx = mysqli_query($conn, "
    SELECT t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan, p.kota
    FROM tbl_transaksi t
    JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Utama - TokoKita Enterprise POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100/70 text-slate-800 font-sans min-h-screen selection:bg-indigo-500 selection:text-white">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        
        <!-- Welcome Hero Banner Modern -->
        <div class="relative overflow-hidden bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 text-white p-6 md:p-8 rounded-3xl shadow-2xl border border-indigo-900/60">
            <!-- Decorative Ambient Glows -->
            <div class="absolute -right-20 -top-20 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-48 -bottom-24 w-72 h-72 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute left-1/3 top-0 w-64 h-64 bg-violet-600/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 px-3 py-1 rounded-full text-xs font-semibold text-indigo-200 backdrop-blur-md">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="font-mono text-[11px]">Sistem Online & Database MariaDB Advanced Aktif</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white leading-tight">
                        Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>! 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-100/80 leading-relaxed font-medium">
                        Pusat kendali Point of Sale, monitoring mutasi saldo member, manajemen inventori katalog barcode, dan audit transaksi TokoKita secara realtime.
                    </p>
                </div>

                <!-- Action Button Shortcuts -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <a href="kasir_pos.php" class="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white px-4 py-3 rounded-2xl text-xs font-black shadow-lg shadow-emerald-500/30 hover:scale-[1.03] active:scale-[0.98] transition flex items-center gap-2 ring-2 ring-emerald-400/40">
                        <i data-lucide="scan-barcode" class="w-4 h-4 text-amber-200 animate-pulse"></i> 
                        <span>Terminal Scanner POS</span>
                    </a>
                    <a href="struk.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-3 rounded-2xl text-xs font-bold backdrop-blur-md transition flex items-center gap-2 hover:scale-[1.02] active:scale-[0.98]">
                        <i data-lucide="printer" class="w-4 h-4 text-slate-300"></i> Cetak Struk 58mm
                    </a>
                    <a href="admin_produk.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-3 rounded-2xl text-xs font-bold backdrop-blur-md transition flex items-center gap-2 hover:scale-[1.02] active:scale-[0.98]">
                        <i data-lucide="package-plus" class="w-4 h-4 text-slate-300"></i> + Master Produk
                    </a>
                </div>
            </div>
        </div>

        <!-- 4 Modern Stat Metric Cards dengan Shadow Halus -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
            <!-- Omzet -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex items-center justify-between group">
                <div class="space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Omzet Penjualan</span>
                    <h3 class="text-2xl font-black text-slate-900 group-hover:text-emerald-600 transition"><?= rupiah($stat_omzet['omzet']) ?></h3>
                    <div class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
                        <span>Terverifikasi Realtime</span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white flex items-center justify-center shadow-lg shadow-emerald-500/25 group-hover:scale-110 transition duration-200">
                    <i data-lucide="badge-dollar-sign" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Transaksi Kasir -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex items-center justify-between group">
                <div class="space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">Pesanan Kasir Sukses</span>
                    <h3 class="text-2xl font-black text-slate-900 group-hover:text-indigo-600 transition">
                        <?= number_format($stat_omzet['jml_trx']) ?> <span class="text-sm font-semibold text-slate-400">Trx</span>
                    </h3>
                    <div class="inline-flex items-center gap-1 text-[11px] text-indigo-600 font-extrabold bg-indigo-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                        <span>Order Selesai</span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-lg shadow-indigo-500/25 group-hover:scale-110 transition duration-200">
                    <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Stok Gudang -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex items-center justify-between group">
                <div class="space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">Inventori Gudang</span>
                    <h3 class="text-2xl font-black text-slate-900 group-hover:text-amber-600 transition">
                        <?= number_format($stat_prod['stok']) ?> <span class="text-sm font-semibold text-slate-400">Unit</span>
                    </h3>
                    <div class="inline-flex items-center gap-1 text-[11px] text-amber-600 font-extrabold bg-amber-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                        <span><?= $stat_prod['item'] ?> Master SKU</span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-400 text-white flex items-center justify-center shadow-lg shadow-amber-500/25 group-hover:scale-110 transition duration-200">
                    <i data-lucide="package" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Saldo Member -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-200 flex items-center justify-between group">
                <div class="space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">Dompet Saldo Member</span>
                    <h3 class="text-2xl font-black text-slate-900 group-hover:text-purple-600 transition"><?= rupiah($stat_topup['total']) ?></h3>
                    <div class="inline-flex items-center gap-1 text-[11px] text-purple-600 font-extrabold bg-purple-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                        <span>Saldo Terhimpun</span>
                    </div>
                </div>
                <div class="w-13 h-13 p-3.5 rounded-2xl bg-gradient-to-tr from-purple-600 to-pink-500 text-white flex items-center justify-center shadow-lg shadow-purple-500/25 group-hover:scale-110 transition duration-200">
                    <i data-lucide="wallet-cards" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <!-- Grid Tengah: Pintasan Modul & Transaksi Kasir Terkini -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Modul Cepat (5 Kolom) -->
            <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="layout-grid" class="w-4 h-4 text-indigo-600"></i> Pintasan Modul Utama
                    </h2>
                    <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wide">12 Fitur Siap</span>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs font-bold">
                    <!-- Scanner POS Banner Link -->
                    <a href="kasir_pos.php" class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-600/20 hover:shadow-lg hover:shadow-emerald-600/30 transition flex items-center justify-between group col-span-2 hover:scale-[1.01] active:scale-[0.99]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center text-white">
                                <i data-lucide="scan-barcode" class="w-4 h-4 animate-pulse"></i>
                            </div>
                            <div>
                                <span class="font-black text-sm block">Scanner Barcode POS</span>
                                <span class="text-[10px] text-emerald-100 font-medium">Scan produk & bayar kasir instan</span>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-white/70 group-hover:translate-x-1 transition"></i>
                    </a>

                    <a href="kasir_pos.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="calculator" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Kasir POS Manual</span>
                    </a>

                    <a href="struk.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Struk 58mm</span>
                    </a>

                    <a href="admin_produk.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="package" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Produk SKU</span>
                    </a>

                    <a href="kategori.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Kategori Barang</span>
                    </a>

                    <a href="saldo.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="wallet-cards" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Dompet Saldo</span>
                    </a>

                    <a href="kasir_riwayat.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="history" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Riwayat Kasir</span>
                    </a>

                    <a href="diskon.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="percent" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Voucher Diskon</span>
                    </a>

                    <a href="aktivitas.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02]">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Log Trigger</span>
                    </a>

                    <a href="laporan.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group hover:scale-[1.02] col-span-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center group-hover:scale-110 transition">
                            <i data-lucide="file-bar-chart" class="w-3.5 h-3.5"></i>
                        </div>
                        <span>Laporan Lanjutan Database SQL & Procedure</span>
                    </a>
                </div>
            </div>

            <!-- Tabel Transaksi Terkini (7 Kolom) -->
            <div class="lg:col-span-7 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                            <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i> Transaksi Kasir Terkini
                        </h2>
                        <p class="text-[11px] text-slate-400">Order terbaru yang tercatat di database</p>
                    </div>
                    <a href="kasir_riwayat.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                        <span>Lihat Semua</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-100 font-extrabold">
                                <th class="p-3">No. TRX</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Tanggal</th>
                                <th class="p-3 text-right">Total Belanja</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php while ($rx = mysqli_fetch_assoc($q_recent_trx)): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3 font-mono font-bold text-indigo-600">
                                    <span class="bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-lg text-[11px]">
                                        #TRX-<?= str_pad($rx['id_transaksi'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-[10px]">
                                            <?= strtoupper(substr($rx['nama_pelanggan'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-800 block leading-tight"><?= htmlspecialchars($rx['nama_pelanggan']) ?></span>
                                            <span class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($rx['kota']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-slate-500 font-medium"><?= $rx['tanggal'] ?></td>
                                <td class="p-3 text-right font-black text-emerald-600"><?= rupiah($rx['total_belanja']) ?></td>
                                <td class="p-3 text-center">
                                    <a href="struk.php?id=<?= $rx['id_transaksi'] ?>" class="inline-flex items-center gap-1 bg-slate-100 hover:bg-indigo-600 hover:text-white text-slate-700 px-2.5 py-1.5 rounded-xl font-bold text-[11px] transition shadow-sm">
                                        <i data-lucide="printer" class="w-3 h-3"></i> Struk 58mm
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Leaderboard Top Member Belanja Terbesar -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="crown" class="w-4 h-4 text-amber-500"></i> Top Member Belanja Terbesar
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-0.5">Integrasi <code>view_total_belanja</code> & Stored Function <code>KategoriBelanja()</code></p>
                </div>
                <a href="pelanggan.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    Master Pelanggan <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                <?php 
                $rank = 1;
                while ($tp = mysqli_fetch_assoc($q_rekap)): 
                    $badge_class = ($tp['kategori'] === 'Pelanggan Gold') 
                        ? 'bg-amber-100 text-amber-800 border-amber-300' 
                        : (($tp['kategori'] === 'Pelanggan Silver') ? 'bg-slate-200 text-slate-700 border-slate-300' : 'bg-indigo-50 text-indigo-700 border-indigo-200');
                    
                    $medal_bg = ($rank === 1) ? 'bg-amber-400 text-white shadow-amber-400/30' : (($rank === 2) ? 'bg-slate-300 text-slate-800 shadow-slate-300/30' : (($rank === 3) ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-600'));
                ?>
                <div class="p-4 rounded-2xl border border-slate-200/70 bg-slate-50/40 hover:bg-white hover:border-indigo-400 hover:shadow-md transition flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-2xl <?= $medal_bg ?> font-black text-xs flex items-center justify-center shadow-md">
                            #<?= $rank++ ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-slate-800 group-hover:text-indigo-600 transition"><?= htmlspecialchars($tp['nama_pelanggan']) ?></h4>
                            <p class="text-[10px] text-slate-400 font-medium"><?= $tp['total_transaksi'] ?>x Transaksi</p>
                            <span class="inline-block mt-1 text-[9px] font-extrabold px-2 py-0.5 rounded-full border <?= $badge_class ?>">
                                <?= $tp['kategori'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 font-medium block">Total Belanja</span>
                        <span class="text-xs font-black text-emerald-600"><?= rupiah($tp['total_belanja']) ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
