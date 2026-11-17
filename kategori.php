<?php
require_once 'functions.php';
cekLogin();

$notif_sukses = "";
$notif_error  = "";

// 1. Tambah Item ke Keranjang Session (Dapat dilakukan oleh Pelanggan & Kasir)
if (isset($_POST['masuk_keranjang'])) {
    if (!isset($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = [];
    }
    $id_produk = (int)$_POST['id_produk'];
    $jumlah    = max(1, (int)$_POST['jumlah']);

    $q_cek = mysqli_query($conn, "SELECT *, HitungTotal(harga, $jumlah) AS subtotal FROM tbl_produk WHERE id_produk = $id_produk");
    $prod  = mysqli_fetch_assoc($q_cek);

    if ($prod) {
        if ($jumlah > $prod['stok']) {
            $notif_error = "Stok tidak mencukupi! Stok {$prod['nama_produk']} tersisa {$prod['stok']} unit.";
        } else {
            if (isset($_SESSION['keranjang'][$id_produk])) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] += $jumlah;
                $jml_baru = $_SESSION['keranjang'][$id_produk]['jumlah'];
                $res_sub  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungTotal({$prod['harga']}, $jml_baru) AS sub"));
                $_SESSION['keranjang'][$id_produk]['subtotal'] = $res_sub['sub'];
            } else {
                $_SESSION['keranjang'][$id_produk] = [
                    'id_produk'   => $prod['id_produk'],
                    'nama_produk' => $prod['nama_produk'],
                    'harga'       => $prod['harga'],
                    'jumlah'      => $jumlah,
                    'subtotal'    => $prod['subtotal']
                ];
            }
            $notif_sukses = "{$prod['nama_produk']} ({$jumlah} item) berhasil dimasukkan ke Keranjang Belanja!";
        }
    }
}

// 2. Tambah Kategori Baru (Khusus Admin / Kasir, Pelanggan dibatasi)
if (isset($_POST['tambah_kategori'])) {
    if (isPelanggan()) {
        $notif_error = "Akses Ditolak: Akun Pelanggan tidak diizinkan menambah kategori baru!";
    } else {
        $nama_kat = mysqli_real_escape_string($conn, trim($_POST['nama_kategori']));
        if (mysqli_query($conn, "INSERT INTO tbl_kategori (nama_kategori) VALUES ('$nama_kat')")) {
            $notif_sukses = "Kategori '$nama_kat' berhasil ditambahkan!";
        } else {
            $notif_error = "Gagal menambah kategori: " . mysqli_error($conn);
        }
    }
}

// 3. Hapus Kategori (Khusus Admin / Kasir, Pelanggan dibatasi)
if (isset($_GET['hapus_kat'])) {
    if (isPelanggan()) {
        $notif_error = "Akses Ditolak: Akun Pelanggan tidak diizinkan menghapus data kategori!";
    } else {
        $id_kat = (int)$_GET['hapus_kat'];
        if (mysqli_query($conn, "DELETE FROM tbl_kategori WHERE id_kategori = $id_kat")) {
            $notif_sukses = "Kategori berhasil dihapus!";
        } else {
            $notif_error = "Kategori tidak bisa dihapus karena masih digunakan oleh data produk.";
        }
    }
}

// 4. Tambah Produk Baru (Khusus Admin / Kasir, Pelanggan dibatasi)
if (isset($_POST['tambah_produk'])) {
    if (isPelanggan()) {
        $notif_error = "Akses Ditolak: Akun Pelanggan tidak diizinkan menambah produk baru!";
    } else {
        $id_kat = (int)$_POST['id_kategori'];
        $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
        $harga  = (float)$_POST['harga'];
        $stok   = (int)$_POST['stok'];

        if (mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES ($id_kat, '$nama', $harga, $stok)")) {
            $notif_sukses = "Produk '$nama' berhasil ditambahkan ke katalog!";
        } else {
            $notif_error = "Gagal menambah produk: " . mysqli_error($conn);
        }
    }
}

// 5. Update Tambah Stok Produk (Khusus Admin / Kasir, Pelanggan dibatasi)
if (isset($_POST['update_stok'])) {
    if (isPelanggan()) {
        $notif_error = "Akses Ditolak: Akun Pelanggan tidak diizinkan mengubah stok produk!";
    } else {
        $id_prod     = (int)$_POST['id_produk'];
        $tambah_stok = (int)$_POST['tambah_stok'];
        mysqli_query($conn, "UPDATE tbl_produk SET stok = stok + $tambah_stok WHERE id_produk = $id_prod");
        $notif_sukses = "Stok produk berhasil diperbarui!";
    }
}

$q_kategori = mysqli_query($conn, "
    SELECT k.*, COUNT(p.id_produk) AS total_produk, COALESCE(SUM(p.stok), 0) AS total_stok
    FROM tbl_kategori k
    LEFT JOIN tbl_produk p ON k.id_kategori = p.id_kategori
    GROUP BY k.id_kategori
");

$q_produk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori, HitungPajak(p.harga) AS ppn_produk
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
");

$jml_keranjang = isset($_SESSION['keranjang']) ? count($_SESSION['keranjang']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori & Produk - TokoKita</title>
    <!-- Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">

    <!-- Navbar Navigasi: Pelanggan HANYA bisa ke Dashboard, Kategori, dan Keranjang -->
    <nav class="bg-slate-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 p-2 rounded-xl shadow-md shadow-indigo-600/30">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h1 class="font-black text-base sm:text-lg tracking-tight">TokoKita Enterprise</h1>
                    <p class="text-[11px] text-slate-400">
                        <?= isPelanggan() ? 'Katalog Belanja Pelanggan' : 'Manajemen Kategori & Inventori Produk' ?>
                    </p>
                </div>
            </div>

            <!-- Menu Navigasi Berdasarkan Role -->
            <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-semibold">
                <!-- 1. DASHBOARD (Pelanggan & Kasir) -->
                <a href="index.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                </a>

                <!-- 2. KATEGORI (Pelanggan & Kasir) -->
                <a href="kategori.php" class="px-3.5 py-2 rounded-xl bg-indigo-600 text-white flex items-center gap-1.5 shadow-md shadow-indigo-600/25">
                    <i data-lucide="layers" class="w-4 h-4"></i> Kategori & Produk
                </a>

                <!-- 3. KERANJANG (Pelanggan & Kasir) -->
                <a href="keranjang.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition relative">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i> 
                    <span><?= isPelanggan() ? 'Keranjang Belanja' : 'Keranjang Kasir' ?></span>
                    <?php if ($jml_keranjang > 0): ?>
                        <span class="bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full"><?= $jml_keranjang ?></span>
                    <?php endif; ?>
                </a>

                <!-- 4. LAPORAN SQL (KHUSUS KASIR & ADMIN - PELANGGAN DIBATASI / TIDAK DITAMPILKAN) -->
                <?php if (!isPelanggan()): ?>
                <a href="laporan.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="file-bar-chart" class="w-4 h-4"></i> Laporan SQL
                </a>
                <?php endif; ?>

                <!-- User Badge -->
                <div class="hidden md:flex items-center gap-2 bg-slate-800/80 px-2.5 py-1.5 rounded-xl border border-slate-700 ml-1 text-[11px]">
                    <span class="w-2 h-2 rounded-full <?= isPelanggan() ? 'bg-amber-400' : 'bg-emerald-400' ?>"></span>
                    <span class="text-slate-200 font-bold"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></span>
                    <span class="text-[9px] uppercase font-extrabold px-1.5 py-0.5 rounded <?= isPelanggan() ? 'bg-amber-500/20 text-amber-300' : 'bg-indigo-500/20 text-indigo-300' ?>">
                        <?= htmlspecialchars($_SESSION['role'] ?? 'Pelanggan') ?>
                    </span>
                </div>

                <!-- Logout -->
                <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari akun?')" class="ml-1 bg-rose-600/90 hover:bg-rose-600 text-white px-3 py-2 rounded-xl flex items-center gap-1.5 transition text-xs font-bold shadow-sm" title="Keluar">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">
        
        <!-- Notifikasi -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-2xl flex items-center justify-between gap-2 text-xs font-bold shadow-sm">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i> 
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="keranjang.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-xl text-xs font-bold transition">
                    Lihat Keranjang →
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <!-- Banner Selamat Datang Khusus Akun Pelanggan -->
        <?php if (isPelanggan()): ?>
            <div class="bg-gradient-to-r from-indigo-700 to-violet-800 text-white p-5 rounded-3xl shadow-lg flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white border border-white/20">
                        <i data-lucide="store" class="w-6 h-6 text-amber-300"></i>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black leading-tight">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h2>
                        <p class="text-xs text-indigo-200 mt-0.5">Silakan pilih produk katalog terbaik kami dan tambahkan ke Keranjang Belanja Anda.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="keranjang.php" class="bg-white text-indigo-900 hover:bg-indigo-50 font-bold px-4 py-2 rounded-2xl text-xs transition shadow flex items-center gap-1.5">
                        <i data-lucide="shopping-cart" class="w-4 h-4 text-indigo-700"></i>
                        <span>Buka Keranjang (<?= $jml_keranjang ?>)</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            <!-- KOLOM KIRI: FORM & DAFTAR KATEGORI -->
            <div class="space-y-5">
                
                <!-- Form Tambah Kategori (Hanya ditampilkan untuk Admin/Kasir, Pelanggan tidak melihat) -->
                <?php if (!isPelanggan()): ?>
                <form method="POST" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-3.5">
                    <h2 class="font-extrabold text-sm flex items-center gap-2 border-b border-slate-100 pb-3 text-slate-800">
                        <i data-lucide="folder-plus" class="w-4 h-4 text-indigo-600"></i> Tambah Kategori (`tbl_kategori`)
                    </h2>
                    <input type="text" name="nama_kategori" placeholder="Nama Kategori (mis: Elektronik)" required 
                        class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-2xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    <button type="submit" name="tambah_kategori" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 rounded-2xl transition shadow-md shadow-indigo-600/20">
                        Simpan Kategori
                    </button>
                </form>
                <?php endif; ?>

                <!-- Daftar Kategori Produk -->
                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-1.5">
                            <i data-lucide="tag" class="w-4 h-4 text-indigo-600"></i> Daftar Kategori
                        </h2>
                        <span class="text-[11px] text-slate-400 font-bold"><?= mysqli_num_rows($q_kategori) ?> Kategori</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-left text-[11px] font-bold text-slate-600 border-b border-slate-200">
                                    <th class="p-2.5">Kategori</th>
                                    <th class="p-2.5 text-center">Item</th>
                                    <th class="p-2.5 text-center">Total Stok</th>
                                    <?php if (!isPelanggan()): ?>
                                    <th class="p-2.5 text-right">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                mysqli_data_seek($q_kategori, 0);
                                while ($kat = mysqli_fetch_assoc($q_kategori)): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-2.5 font-bold text-slate-800"><?= htmlspecialchars($kat['nama_kategori']) ?></td>
                                    <td class="p-2.5 text-center font-semibold text-slate-600"><?= $kat['total_produk'] ?></td>
                                    <td class="p-2.5 text-center">
                                        <span class="bg-slate-100 font-bold px-2 py-0.5 rounded-md text-slate-700"><?= $kat['total_stok'] ?></span>
                                    </td>
                                    <?php if (!isPelanggan()): ?>
                                    <td class="p-2.5 text-right">
                                        <a href="?hapus_kat=<?= $kat['id_kategori'] ?>" onclick="return confirm('Hapus kategori ini?')" class="text-rose-600 hover:text-rose-700 text-[11px] font-bold hover:underline">
                                            Hapus
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- KOLOM KANAN: FORM PRODUK (ADMIN ONLY) & KATALOG BELANJA -->
            <div class="lg:col-span-2 space-y-5">
                
                <!-- Form Tambah Produk Baru (Hanya untuk Admin/Kasir, Pelanggan tidak melihat) -->
                <?php if (!isPelanggan()): ?>
                <form method="POST" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-3.5">
                    <h2 class="font-extrabold text-sm flex items-center gap-2 border-b border-slate-100 pb-3 text-slate-800">
                        <i data-lucide="package-plus" class="w-4 h-4 text-emerald-600"></i> Tambah Produk Baru (`tbl_produk`)
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                        <select name="id_kategori" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-2xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Kategori --</option>
                            <?php 
                            $q_kat_opt = mysqli_query($conn, "SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC");
                            while ($ko = mysqli_fetch_assoc($q_kat_opt)): ?>
                                <option value="<?= $ko['id_kategori'] ?>"><?= htmlspecialchars($ko['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="nama_produk" placeholder="Nama Produk" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-2xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <input type="number" name="harga" placeholder="Harga (Rp)" min="100" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-2xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <input type="number" name="stok" placeholder="Stok Awal" min="1" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-2xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <button type="submit" name="tambah_produk" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-6 rounded-2xl transition shadow-md shadow-emerald-600/20 flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah ke Katalog Produk
                    </button>
                </form>
                <?php endif; ?>

                <!-- Katalog Produk -->
                <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-2">
                                <i data-lucide="package" class="w-4 h-4 text-indigo-600"></i>
                                <?= isPelanggan() ? 'Katalog Produk Belanja' : 'Katalog Produk (`tbl_produk` + Function `HitungPajak`)' ?>
                            </h2>
                            <p class="text-[11px] text-slate-400">
                                <?= isPelanggan() ? 'Pilih jumlah pesanan dan klik tombol Tambah ke Keranjang' : 'Daftar inventori stok aktif yang terintegrasi database' ?>
                            </p>
                        </div>
                        <a href="keranjang.php" class="text-indigo-600 hover:text-indigo-700 text-xs font-bold flex items-center gap-1">
                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Menuju Keranjang →
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-left text-[11px] font-bold text-slate-600 border-b border-slate-200">
                                    <th class="p-3">Produk</th>
                                    <th class="p-3">Kategori</th>
                                    <th class="p-3">Harga Dasar</th>
                                    <th class="p-3">PPN 11%</th>
                                    <th class="p-3">Stok</th>
                                    <th class="p-3 text-center"><?= isPelanggan() ? 'Pesan / Beli' : 'Restock Cepat' ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php 
                                mysqli_data_seek($q_produk, 0);
                                while ($pr = mysqli_fetch_assoc($q_produk)): 
                                    $habis = $pr['stok'] <= 0;
                                ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-3 font-bold text-slate-800">
                                        <?= htmlspecialchars($pr['nama_produk']) ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold uppercase">
                                            <?= htmlspecialchars($pr['nama_kategori'] ?? 'Umum') ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-black text-emerald-600"><?= rupiah($pr['harga']) ?></td>
                                    <td class="p-3 text-slate-400 font-medium"><?= rupiah($pr['ppn_produk']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-1 rounded-xl text-[11px] font-bold <?= $habis ? 'bg-slate-200 text-slate-600' : ($pr['stok'] <= 10 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') ?>">
                                            <?= $habis ? 'Habis' : $pr['stok'] . ' Unit' ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <?php if (isPelanggan()): ?>
                                            <!-- Tombol Beli / Tambah ke Keranjang untuk Pelanggan -->
                                            <?php if ($habis): ?>
                                                <span class="text-slate-400 text-[11px] italic">Stok Habis</span>
                                            <?php else: ?>
                                                <form method="POST" class="flex items-center justify-center gap-1.5">
                                                    <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                                                    <input type="number" name="jumlah" value="1" min="1" max="<?= $pr['stok'] ?>" 
                                                        class="w-12 border border-slate-300 rounded-xl p-1 text-center font-bold text-xs focus:ring-1 focus:ring-indigo-500">
                                                    <button type="submit" name="masuk_keranjang" 
                                                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold px-2.5 py-1.5 rounded-xl shadow-sm flex items-center gap-1 transition active:scale-95">
                                                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Beli
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Form Restock Cepat untuk Admin / Kasir -->
                                            <form method="POST" class="flex items-center justify-center gap-1">
                                                <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                                                <input type="number" name="tambah_stok" value="5" min="1" class="w-14 border border-slate-300 rounded-xl p-1 text-xs text-center font-bold">
                                                <button type="submit" name="update_stok" class="bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-bold px-2.5 py-1 rounded-xl transition">
                                                    +Stok
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>