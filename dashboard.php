<?php
require_once 'functions.php';
cekLogin();

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
    ORDER BY t.id_transaksi DESC LIMIT 5
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
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen selection:bg-indigo-500 selection:text-white">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Welcome Hero Banner -->
        <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-indigo-950 to-indigo-900 text-white p-6 md:p-8 rounded-3xl shadow-xl border border-indigo-800/40">
            <!-- Decorative Glow -->
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute right-40 -bottom-20 w-60 h-60 bg-violet-500/20 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div class="space-y-1.5 max-w-xl">
                    <div class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 px-3 py-1 rounded-full text-xs font-semibold text-indigo-200 backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Sistem Online • MariaDB Advanced SQL Active</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white">
                        Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>! 👋
                    </h1>
                    <p class="text-xs md:text-sm text-indigo-100/80 leading-relaxed">
                        Pusat kendali Point of Sale, monitoring mutasi saldo member, manajemen inventori katalog, dan audit log otomatis TokoKita.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="kasir_pos.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2.5 rounded-2xl text-xs font-bold shadow-lg shadow-indigo-600/30 hover:scale-[1.02] active:scale-[0.98] transition flex items-center gap-2">
                        <i data-lucide="calculator" class="w-4 h-4"></i> Buka Terminal POS
                    </a>
                    <a href="struk.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-2xl text-xs font-bold backdrop-blur-sm transition flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i> Cetak Struk 58mm
                    </a>
                    <a href="admin_produk.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-4 py-2.5 rounded-2xl text-xs font-bold backdrop-blur-sm transition flex items-center gap-2">
                        <i data-lucide="package-plus" class="w-4 h-4"></i> Tambah Produk
                    </a>
                </div>
            </div>
        </div>

        <!-- 4 Modern Stat Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
            <!-- Omzet -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Omzet Penjualan</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1"><?= rupiah($stat_omzet['omzet']) ?></h3>
                    <p class="text-[11px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                        <i data-lucide="trending-up" class="w-3.5 h-3.5"></i> Transaksi terverifikasi
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <i data-lucide="badge-dollar-sign" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Transaksi -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Pesanan Kasir</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1"><?= number_format($stat_omzet['jml_trx']) ?> <span class="text-sm font-semibold text-slate-400">Trx</span></h3>
                    <p class="text-[11px] text-indigo-600 font-bold mt-1 flex items-center gap-1">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Order selesai & dicatat
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-md shadow-indigo-500/20">
                    <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Stok Gudang -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Stok Inventori</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1"><?= number_format($stat_prod['stok']) ?> <span class="text-sm font-semibold text-slate-400">Unit</span></h3>
                    <p class="text-[11px] text-amber-600 font-bold mt-1 flex items-center gap-1">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i> <?= $stat_prod['item'] ?> Master SKU
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-400 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
                    <i data-lucide="package" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Saldo Member -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Dompet Member</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1"><?= rupiah($stat_topup['total']) ?></h3>
                    <p class="text-[11px] text-purple-600 font-bold mt-1 flex items-center gap-1">
                        <i data-lucide="wallet" class="w-3.5 h-3.5"></i> Saldo tersimpan di sistem
                    </p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-pink-500 text-white flex items-center justify-center shadow-md shadow-purple-500/20">
                    <i data-lucide="wallet-cards" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <!-- Grid Tengah: Navigasi Cepat Modul & Transaksi Terkini -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Modul Cepat -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="grid" class="w-4 h-4 text-indigo-600"></i> Pintasan Modul Sistem
                    </h2>
                    <span class="text-[11px] font-bold text-slate-400">12 Modul Aktif</span>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs font-bold">
                    <a href="kasir_pos.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="calculator" class="w-4 h-4 text-indigo-600 group-hover:scale-110 transition"></i>
                        <span>Kasir POS</span>
                    </a>
                    <a href="struk.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="receipt" class="w-4 h-4 text-amber-600 group-hover:scale-110 transition"></i>
                        <span>Struk 58mm</span>
                    </a>
                    <a href="admin_produk.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="package" class="w-4 h-4 text-emerald-600 group-hover:scale-110 transition"></i>
                        <span>Produk SKU</span>
                    </a>
                    <a href="saldo.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="wallet-cards" class="w-4 h-4 text-purple-600 group-hover:scale-110 transition"></i>
                        <span>Dompet Saldo</span>
                    </a>
                    <a href="kasir_riwayat.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="history" class="w-4 h-4 text-cyan-600 group-hover:scale-110 transition"></i>
                        <span>Riwayat Kasir</span>
                    </a>
                    <a href="diskon.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="percent" class="w-4 h-4 text-rose-600 group-hover:scale-110 transition"></i>
                        <span>Voucher Diskon</span>
                    </a>
                    <a href="aktivitas.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="activity" class="w-4 h-4 text-amber-600 group-hover:scale-110 transition"></i>
                        <span>Log Trigger</span>
                    </a>
                    <a href="laporan.php" class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200/70 transition flex items-center gap-2 group">
                        <i data-lucide="file-bar-chart" class="w-4 h-4 text-indigo-600 group-hover:scale-110 transition"></i>
                        <span>Laporan SQL</span>
                    </a>
                </div>
            </div>

            <!-- Tabel Transaksi Terkini -->
            <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i> Transaksi Kasir Terkini
                    </h2>
                    <a href="kasir_riwayat.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        Lihat Semua <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 uppercase tracking-wider text-[10px] border-b border-slate-100">
                                <th class="p-3">No. Transaksi</th>
                                <th class="p-3">Pelanggan</th>
                                <th class="p-3">Tanggal</th>
                                <th class="p-3 text-right">Total Belanja</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php while ($rx = mysqli_fetch_assoc($q_recent_trx)): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="p-3 font-mono font-bold text-indigo-600">#TRX-<?= str_pad($rx['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td class="p-3 font-semibold text-slate-800">
                                    <?= htmlspecialchars($rx['nama_pelanggan']) ?>
                                    <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($rx['kota']) ?></span>
                                </td>
                                <td class="p-3 text-slate-500"><?= $rx['tanggal'] ?></td>
                                <td class="p-3 text-right font-extrabold text-emerald-600"><?= rupiah($rx['total_belanja']) ?></td>
                                <td class="p-3 text-center">
                                    <a href="struk.php?id=<?= $rx['id_transaksi'] ?>" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-lg font-bold text-[11px] transition">
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

        <!-- Pelanggan Terbaik Berdasarkan Belanja (View + Stored Function) -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="crown" class="w-4 h-4 text-amber-500"></i> Top Member Belanja Terbesar
                    </h2>
                    <p class="text-[11px] text-slate-400 mt-0.5">Integrasi <code>view_total_belanja</code> dan Stored Function <code>KategoriBelanja()</code></p>
                </div>
                <a href="pelanggan.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                    Master Pelanggan <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php 
                $rank = 1;
                while ($tp = mysqli_fetch_assoc($q_rekap)): 
                    $badge_class = ($tp['kategori'] === 'Pelanggan Gold') 
                        ? 'bg-amber-100 text-amber-800 border-amber-300' 
                        : (($tp['kategori'] === 'Pelanggan Silver') ? 'bg-slate-200 text-slate-700 border-slate-300' : 'bg-indigo-50 text-indigo-700 border-indigo-200');
                ?>
                <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-slate-200 hover:shadow-md transition flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center">
                            #<?= $rank++ ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-slate-800"><?= htmlspecialchars($tp['nama_pelanggan']) ?></h4>
                            <p class="text-[10px] text-slate-400"><?= $tp['total_transaksi'] ?>x Transaksi Sukses</p>
                            <span class="inline-block mt-1 text-[9px] font-extrabold px-2 py-0.5 rounded-full border <?= $badge_class ?>">
                                <?= $tp['kategori'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-slate-400 block">Total Belanja</span>
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
