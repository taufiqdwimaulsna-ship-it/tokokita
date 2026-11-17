<?php
require_once 'functions.php';
cekLogin();

$notif_sukses = "";
$notif_error  = "";

// Notifikasi pembatasan hak akses jika pelanggan mencoba buka halaman terlarang
if (isset($_SESSION['pesan_akses'])) {
    $notif_error = $_SESSION['pesan_akses'];
    unset($_SESSION['pesan_akses']);
}

// 1. Tambah Pelanggan via Stored Procedure
if (isset($_POST['tambah_pelanggan'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_pelanggan']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);

    if (mysqli_query($conn, "CALL TambahPelanggan('$nama', '$kota')")) {
        $notif_sukses = "Pelanggan '$nama' berhasil ditambahkan melalui Stored Procedure!";
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
    } else {
        $notif_error = "Gagal: " . mysqli_error($conn);
    }
}

// 2. Transaksi Baru via Stored Procedure + Function + Trigger Stok
if (isset($_POST['tambah_transaksi'])) {
    $id_pelanggan = (int)$_POST['id_pelanggan'];
    $id_produk    = (int)$_POST['id_produk'];
    $jumlah       = (int)$_POST['jumlah'];
    $diskon       = (float)$_POST['diskon'];

    if (mysqli_query($conn, "CALL sp_TambahTransaksiBaru($id_pelanggan, $id_produk, $jumlah, $diskon)")) {
        $notif_sukses = "Transaksi berhasil! Function HitungTotal & HitungDiskon dijalankan, stok otomatis berkurang.";
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
    } else {
        $notif_error = "Gagal: " . mysqli_error($conn);
    }
}

// 3. Uji Trigger Validasi Transaksi Negatif
if (isset($_POST['uji_negatif'])) {
    $sql = "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES (CURDATE(), 1, -50000)";
    if (!mysqli_query($conn, $sql)) {
        $notif_error = "Trigger Validasi Aktif (Ditolak Database): " . mysqli_error($conn);
    }
}

// Statistik Ringkas untuk Kartu Dashboard Atas
$stat_omzet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_belanja),0) AS omzet, COUNT(*) AS jml_trx FROM tbl_transaksi"));
$stat_pel   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml_pel FROM tbl_pelanggan"));
$stat_prod  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(stok),0) AS total_stok FROM tbl_produk"));

// Query Laporan Utama
$q_rekap = mysqli_query($conn, "
    SELECT id_pelanggan, nama_pelanggan, total_transaksi, total_belanja,
           HitungPajak(total_belanja) AS estimasi_ppn,
           KategoriBelanja(total_belanja) AS kategori
    FROM view_total_belanja
");
$q_transaksi = mysqli_query($conn, "SELECT * FROM view_transaksi_pelanggan ORDER BY id_transaksi DESC LIMIT 7");
$q_subquery  = mysqli_query($conn, "
    SELECT t.id_transaksi, p.nama_pelanggan, t.tanggal, t.total_belanja 
    FROM tbl_transaksi t JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE t.total_belanja > (SELECT AVG(total_belanja) FROM tbl_transaksi)
    ORDER BY t.total_belanja DESC LIMIT 5
");
$q_produk = mysqli_query($conn, "SELECT * FROM tbl_produk");
$q_log    = mysqli_query($conn, "SELECT * FROM log_pelanggan ORDER BY id_log DESC LIMIT 4");
$q_audit  = mysqli_query($conn, "SELECT * FROM tbl_log ORDER BY id_log DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard POS - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">

 
   
        <!-- Navbar Atas dengan Navigasi Lengkap & Tombol Logout -->
    <nav class="bg-slate-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 p-2 rounded-xl">
                    <i data-lucide="shopping-bag" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg tracking-tight">TokoKita Enterprise</h1>
                    <p class="text-xs text-slate-400">Advanced Database Programming • db_tokokita</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <a href="index.php" class="px-3.5 py-2 rounded-xl bg-indigo-600 text-white flex items-center gap-1.5 shadow">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                </a>
                <a href="kategori.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="layers" class="w-4 h-4"></i> Kategori & Produk
                </a>
                <a href="keranjang.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i> <?= isPelanggan() ? 'Keranjang Belanja' : 'Keranjang Kasir' ?>
                </a>

                <?php if (!isPelanggan()): ?>
                <a href="laporan.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="file-bar-chart" class="w-4 h-4"></i> Laporan SQL
                </a>

                <form method="POST" class="inline">
                    <button type="submit" name="uji_negatif" class="bg-amber-500/20 border border-amber-500/40 hover:bg-amber-500/30 text-amber-300 px-3 py-2 rounded-xl flex items-center gap-1.5 transition">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i> Uji Trigger
                    </button>
                </form>
                <?php endif; ?>

                <div class="hidden md:flex items-center gap-2 bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-700">
                    <i data-lucide="user-check" class="w-4 h-4 text-emerald-400"></i>
                    <div class="text-xs">
                        <p class="font-semibold text-white"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></p>
                        <p class="text-[10px] text-slate-400"><?= htmlspecialchars($_SESSION['role'] ?? 'Pelanggan') ?></p>
                    </div>
                </div>

                <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')"
                   class="bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2 rounded-xl flex items-center gap-1.5 shadow-lg shadow-rose-600/30 transition">
                    <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6 space-y-6">

        <!-- Notifikasi -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl flex items-center gap-2 text-sm font-medium shadow-sm">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i> <?= $notif_sukses ?>
            </div>
        <?php endif; ?>
        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2 text-sm font-medium shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i> <?= $notif_error ?>
            </div>
        <?php endif; ?>

        <!-- 4 Stat Cards Ala Retaillet Dashboard (Bab 6) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white p-5 rounded-2xl shadow-md flex justify-between items-center">
                <div>
                    <p class="text-xs font-medium text-amber-100 uppercase">Total Penjualan</p>
                    <h3 class="text-2xl font-extrabold mt-1">Rp <?= number_format($stat_omzet['omzet'], 0, ',', '.') ?></h3>
                </div>
                <div class="bg-white/20 p-3 rounded-xl"><i data-lucide="wallet" class="w-6 h-6"></i></div>
            </div>

            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 text-white p-5 rounded-2xl shadow-md flex justify-between items-center">
                <div>
                    <p class="text-xs font-medium text-indigo-100 uppercase">Total Transaksi</p>
                    <h3 class="text-2xl font-extrabold mt-1"><?= $stat_omzet['jml_trx'] ?> Pesanan</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-xl"><i data-lucide="receipt" class="w-6 h-6"></i></div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-5 rounded-2xl shadow-md flex justify-between items-center">
                <div>
                    <p class="text-xs font-medium text-emerald-100 uppercase">Total Pelanggan</p>
                    <h3 class="text-2xl font-extrabold mt-1"><?= $stat_pel['jml_pel'] ?> Member</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-xl"><i data-lucide="users" class="w-6 h-6"></i></div>
            </div>

            <div class="bg-gradient-to-br from-purple-600 to-fuchsia-700 text-white p-5 rounded-2xl shadow-md flex justify-between items-center">
                <div>
                    <p class="text-xs font-medium text-purple-100 uppercase">Stok Barang Tersedia</p>
                    <h3 class="text-2xl font-extrabold mt-1"><?= $stat_prod['total_stok'] ?> Unit</h3>
                </div>
                <div class="bg-white/20 p-3 rounded-xl"><i data-lucide="package-check" class="w-6 h-6"></i></div>
            </div>
        </div>

        <!-- Aksi Dashboard: Disesuaikan Berdasarkan Role Pengguna -->
        <?php if (isPelanggan()): ?>
        <!-- Panel Navigasi Cepat Khusus Akun Pelanggan (Hanya Dashboard, Kategori, dan Keranjang) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-900 text-white p-6 rounded-3xl shadow-md border border-indigo-800/40 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-amber-300 mb-3">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-base font-black">Katalog Kategori & Produk</h2>
                    <p class="text-xs text-indigo-200 mt-1">Jelajahi berbagai pilihan produk kebutuhan harian dengan harga bersaing dan transparansi PPN.</p>
                </div>
                <div class="pt-3">
                    <a href="kategori.php" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-2xl text-xs font-bold transition inline-flex items-center gap-2 shadow-lg shadow-indigo-600/30">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Buka Katalog Produk →
                    </a>
                </div>
            </div>

            <div class="bg-gradient-to-br from-emerald-900 via-emerald-950 to-slate-900 text-white p-6 rounded-3xl shadow-md border border-emerald-800/40 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 mb-3">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-base font-black">Keranjang Belanja Pelanggan</h2>
                    <p class="text-xs text-emerald-200 mt-1">Periksa barang belanjaan yang telah Anda pilih dan selesaikan pesanan dengan mudah.</p>
                </div>
                <div class="pt-3">
                    <a href="keranjang.php" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 rounded-2xl text-xs font-bold transition inline-flex items-center gap-2 shadow-lg shadow-emerald-600/30">
                        <i data-lucide="arrow-right-circle" class="w-4 h-4"></i> Buka Keranjang Belanja →
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Form Stored Procedure Khusus Administrator & Kasir POS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center gap-2 border-b pb-3">
                    <i data-lucide="user-plus" class="w-5 h-5 text-indigo-600"></i>
                    <h2 class="font-bold text-slate-800">Tambah Pelanggan (`CALL TambahPelanggan`)</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" name="nama_pelanggan" placeholder="Nama Pelanggan" required class="border border-slate-200 p-2.5 rounded-xl text-sm">
                    <input type="text" name="kota" placeholder="Kota (mis: Ponorogo)" required class="border border-slate-200 p-2.5 rounded-xl text-sm">
                </div>
                <button type="submit" name="tambah_pelanggan" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-xl w-full transition">
                    Simpan Pelanggan Baru
                </button>
            </form>

            <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center gap-2 border-b pb-3">
                    <i data-lucide="shopping-cart" class="w-5 h-5 text-emerald-600"></i>
                    <h2 class="font-bold text-slate-800">Transaksi Kasir (`CALL sp_TambahTransaksiBaru`)</h2>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <select name="id_pelanggan" required class="border border-slate-200 p-2.5 rounded-xl text-sm">
                        <?php 
                        $q_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan");
                        while ($p = mysqli_fetch_assoc($q_pel)): ?>
                            <option value="<?= $p['id_pelanggan'] ?>"><?= $p['nama_pelanggan'] ?> (<?= $p['kota'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                    <select name="id_produk" required class="border border-slate-200 p-2.5 rounded-xl text-sm">
                        <?php while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                            <option value="<?= $pr['id_produk'] ?>"><?= $pr['nama_produk'] ?> (Stok: <?= $pr['stok'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="jumlah" value="1" min="1" placeholder="Jumlah" required class="border border-slate-200 p-2.5 rounded-xl text-sm">
                    <input type="number" name="diskon" value="0" min="0" max="100" placeholder="Diskon (%)" class="border border-slate-200 p-2.5 rounded-xl text-sm">
                </div>
                <button type="submit" name="tambah_transaksi" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 px-4 rounded-xl w-full transition">
                    Proses Transaksi & Potong Stok Otomatis
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Tabel View Total Belanja & Function -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="font-bold text-base mb-4 flex items-center gap-2">
                <i data-lucide="award" class="w-5 h-5 text-amber-500"></i>
                Rekapitulasi Belanja Pelanggan (`view_total_belanja` + Function `KategoriBelanja` & `HitungPajak`)
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600">
                            <th class="p-3 border-b">ID</th>
                            <th class="p-3 border-b">Nama Pelanggan</th>
                            <th class="p-3 border-b">Frekuensi</th>
                            <th class="p-3 border-b">Total Belanja</th>
                            <th class="p-3 border-b">PPN 11% (Function)</th>
                            <th class="p-3 border-b">Status Member (Function)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($q_rekap)): ?>
                        <tr class="hover:bg-slate-50 border-b">
                            <td class="p-3">#<?= $row['id_pelanggan'] ?></td>
                            <td class="p-3 font-semibold"><?= $row['nama_pelanggan'] ?></td>
                            <td class="p-3"><?= $row['total_transaksi'] ?>x Transaksi</td>
                            <td class="p-3 font-bold text-slate-800">Rp <?= number_format($row['total_belanja'], 0, ',', '.') ?></td>
                            <td class="p-3 text-slate-500">Rp <?= number_format($row['estimasi_ppn'], 0, ',', '.') ?></td>
                            <td class="p-3">
                                <span class="px-3 py-1 text-xs rounded-full font-bold
                                    <?= $row['kategori'] == 'Pelanggan Gold' ? 'bg-amber-100 text-amber-800' : ($row['kategori'] == 'Pelanggan Silver' ? 'bg-slate-200 text-slate-800' : 'bg-blue-100 text-blue-700') ?>">
                                    <?= $row['kategori'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3 Kolom Bawah: View Transaksi, Subquery, & Trigger Log -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-sm mb-3 text-slate-700">Riwayat Transaksi (`view_transaksi_pelanggan`)</h3>
                <table class="w-full text-xs">
                    <thead><tr class="bg-slate-100"><th class="p-2 text-left">Tanggal</th><th class="p-2 text-left">Pelanggan</th><th class="p-2 text-right">Total</th></tr></thead>
                    <tbody>
                        <?php while ($tr = mysqli_fetch_assoc($q_transaksi)): ?>
                        <tr class="border-b"><td class="p-2"><?= $tr['tanggal'] ?></td><td class="p-2"><?= $tr['nama_pelanggan'] ?></td><td class="p-2 text-right font-semibold">Rp<?= number_format($tr['total_belanja'], 0, ',', '.') ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-sm mb-3 text-slate-700">Transaksi Di Atas Rata-Rata (`Subquery AVG`)</h3>
                <table class="w-full text-xs">
                    <thead><tr class="bg-slate-100"><th class="p-2 text-left">ID</th><th class="p-2 text-left">Pelanggan</th><th class="p-2 text-right">Nominal</th></tr></thead>
                    <tbody>
                        <?php while ($sq = mysqli_fetch_assoc($q_subquery)): ?>
                        <tr class="border-b"><td class="p-2">#<?= $sq['id_transaksi'] ?></td><td class="p-2"><?= $sq['nama_pelanggan'] ?></td><td class="p-2 text-right font-bold text-emerald-600">Rp<?= number_format($sq['total_belanja'], 0, ',', '.') ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-sm mb-3 text-slate-700">Log Aktivitas Otomatis (`Trigger Database`)</h3>
                <table class="w-full text-xs mb-3">
                    <thead><tr class="bg-slate-100"><th class="p-2 text-left">Aksi Pelanggan</th><th class="p-2 text-right">Waktu</th></tr></thead>
                    <tbody>
                        <?php while ($lg = mysqli_fetch_assoc($q_log)): ?>
                        <tr class="border-b"><td class="p-2 font-mono text-indigo-600"><?= $lg['aktivitas'] ?> (ID: <?= $lg['id_pelanggan'] ?>)</td><td class="p-2 text-right text-slate-400"><?= $lg['waktu'] ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <table class="w-full text-xs">
                    <thead><tr class="bg-slate-100"><th class="p-2 text-left">Log Potong Stok</th><th class="p-2 text-right">Waktu</th></tr></thead>
                    <tbody>
                        <?php while ($au = mysqli_fetch_assoc($q_audit)): ?>
                        <tr class="border-b"><td class="p-2"><?= $au['keterangan'] ?></td><td class="p-2 text-right text-slate-400"><?= $au['waktu'] ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>