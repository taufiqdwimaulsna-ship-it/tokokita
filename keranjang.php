<?php
require_once 'functions.php';
cekLogin();

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$notif_sukses = "";
$notif_error  = "";

// 1. Tambah Item ke Keranjang Session
if (isset($_POST['masuk_keranjang'])) {
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
            $notif_sukses = "{$prod['nama_produk']} berhasil ditambahkan ke keranjang belanja!";
        }
    }
}

// 2. Hapus 1 Item atau Kosongkan Keranjang
if (isset($_GET['hapus_item'])) {
    $id_hapus = (int)$_GET['hapus_item'];
    unset($_SESSION['keranjang'][$id_hapus]);
    header("Location: keranjang.php");
    exit;
}
if (isset($_GET['kosongkan'])) {
    $_SESSION['keranjang'] = [];
    header("Location: keranjang.php");
    exit;
}

// 3. Proses Checkout Keranjang dengan TCL (START TRANSACTION, COMMIT, ROLLBACK) + Trigger
if (isset($_POST['checkout_keranjang'])) {
    if (empty($_SESSION['keranjang'])) {
        $notif_error = "Keranjang masih kosong, silakan pilih produk terlebih dahulu.";
    } else {
        $id_pelanggan  = (int)$_POST['id_pelanggan'];
        $diskon_persen = (float)$_POST['diskon_persen'];

        // Hitung Grand Total dari Keranjang
        $total_sebelum_diskon = 0;
        foreach ($_SESSION['keranjang'] as $item) {
            $total_sebelum_diskon += $item['subtotal'];
        }

        // Jalankan Function Database HitungDiskon
        $q_hitung = mysqli_query($conn, "
            SELECT ($total_sebelum_diskon - HitungDiskon($total_sebelum_diskon, $diskon_persen)) AS total_bersih
        ");
        $kalkulasi = mysqli_fetch_assoc($q_hitung);
        $total_bersih = $kalkulasi['total_bersih'];

        // Mulai Transaksi Database (Bab 6: TCL COMMIT & ROLLBACK)
        mysqli_begin_transaction($conn);

        $ok_header = mysqli_query($conn, "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES (CURDATE(), $id_pelanggan, $total_bersih)");
        $id_trx_baru = mysqli_insert_id($conn);

        $ok_detail = true;
        foreach ($_SESSION['keranjang'] as $item) {
            $id_p = $item['id_produk'];
            $nm_p = mysqli_real_escape_string($conn, $item['nama_produk']);
            $jml  = $item['jumlah'];
            $hrg  = $item['harga'];

            // Cek apakah stok masih cukup sebelum commit
            $cek_stok = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok FROM tbl_produk WHERE id_produk = $id_p"));
            if ($cek_stok['stok'] < $jml) {
                $ok_detail = false;
                break;
            }

            // Insert ke tbl_detail_transaksi (Otomatis memicu Trigger trg_kurangi_stok_dan_audit)
            $ins_det = mysqli_query($conn, "
                INSERT INTO tbl_detail_transaksi (id_transaksi, id_produk, nama_produk, jumlah, harga)
                VALUES ($id_trx_baru, $id_p, '$nm_p', $jml, $hrg)
            ");
            if (!$ins_det) {
                $ok_detail = false;
                break;
            }
        }

        if ($ok_header && $ok_detail) {
            mysqli_commit($conn);
            $_SESSION['keranjang'] = [];
            $notif_sukses = "Checkout Berhasil (TCL COMMIT)! Transaksi #TRX-$id_trx_baru tersimpan & Stok produk otomatis terpotong.";
        } else {
            mysqli_rollback($conn);
            $notif_error = "Checkout Dibatalkan (TCL ROLLBACK)! Terjadi kendala stok produk tidak mencukupi.";
        }
    }
}

$q_produk    = mysqli_query($conn, "SELECT p.*, k.nama_kategori FROM tbl_produk p LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori WHERE p.stok > 0 ORDER BY p.id_produk DESC");
$q_pelanggan = mysqli_query($conn, "SELECT * FROM view_pelanggan ORDER BY nama_pelanggan ASC");

// Cari ID Pelanggan dari session login jika role Pelanggan
$id_pel_login = 0;
if (isPelanggan()) {
    $nama_user = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);
    $qp = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_pelanggan WHERE nama_pelanggan = '$nama_user' LIMIT 1");
    if ($rp = mysqli_fetch_assoc($qp)) {
        $id_pel_login = (int)$rp['id_pelanggan'];
    }
    // Jika tidak ditemukan tepat nama, ambil fallback
    if ($id_pel_login === 0) {
        $qp_any = mysqli_query($conn, "SELECT id_pelanggan FROM tbl_pelanggan LIMIT 1");
        if ($rp_any = mysqli_fetch_assoc($qp_any)) {
            $id_pel_login = (int)$rp_any['id_pelanggan'];
        }
    }
}

$jml_keranjang = count($_SESSION['keranjang']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - TokoKita</title>
    <!-- Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">

    <!-- Navbar: Pelanggan HANYA bisa ke Dashboard, Kategori, dan Keranjang -->
    <nav class="bg-slate-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 p-2 rounded-xl shadow-md shadow-indigo-600/30">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h1 class="font-black text-base sm:text-lg tracking-tight">TokoKita Enterprise</h1>
                    <p class="text-[11px] text-slate-400">
                        <?= isPelanggan() ? 'Keranjang Belanja Pelanggan' : 'Point of Sale (POS) • Multi-Item Cart' ?>
                    </p>
                </div>
            </div>

            <!-- Menu Navigasi Sesuai Role -->
            <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-semibold">
                <!-- 1. DASHBOARD -->
                <a href="index.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                </a>

                <!-- 2. KATEGORI & PRODUK -->
                <a href="kategori.php" class="px-3.5 py-2 rounded-xl text-slate-300 hover:bg-slate-800 flex items-center gap-1.5 transition">
                    <i data-lucide="layers" class="w-4 h-4"></i> Kategori & Produk
                </a>

                <!-- 3. KERANJANG -->
                <a href="keranjang.php" class="px-3.5 py-2 rounded-xl bg-indigo-600 text-white flex items-center gap-1.5 shadow-md shadow-indigo-600/25 relative">
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
        
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-2xl flex items-center justify-between gap-2 text-xs font-bold shadow-sm">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i> 
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="kategori.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-xl text-xs font-bold transition">
                    + Belanja Lagi
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            <!-- Katalog Pilih Produk (Kiri - 2 Kolom) -->
            <div class="lg:col-span-2 bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="grid" class="w-4 h-4 text-indigo-600"></i> Pilih Produk ke Keranjang
                    </h2>
                    <a href="kategori.php" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">
                        Lihat Semua Kategori →
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 max-h-[620px] overflow-y-auto pr-1">
                    <?php while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                    <form method="POST" class="border border-slate-200/70 rounded-2xl p-4 hover:border-indigo-500 hover:shadow-md transition flex flex-col justify-between bg-white group">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-md">
                                    <?= htmlspecialchars($pr['nama_kategori'] ?? 'Umum') ?>
                                </span>
                                <span class="text-[10px] font-bold text-slate-400">
                                    Stok: <?= $pr['stok'] ?>
                                </span>
                            </div>
                            <h3 class="font-bold text-xs text-slate-800 group-hover:text-indigo-600 transition line-clamp-1">
                                <?= htmlspecialchars($pr['nama_produk']) ?>
                            </h3>
                            <p class="text-emerald-600 font-black text-sm mt-1"><?= rupiah($pr['harga']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 mt-3 pt-2.5 border-t border-slate-100">
                            <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                            <input type="number" name="jumlah" value="1" min="1" max="<?= $pr['stok'] ?>" 
                                class="w-14 border border-slate-300 rounded-xl p-1.5 text-xs text-center font-bold focus:ring-1 focus:ring-indigo-500">
                            <button type="submit" name="masuk_keranjang" 
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2 px-3 rounded-xl flex items-center justify-center gap-1.5 transition active:scale-95 shadow-sm">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Tambah
                            </button>
                        </div>
                    </form>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Panel Rincian Keranjang & Checkout (Kanan - 1 Kolom) -->
            <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200/80 space-y-4 sticky top-20">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h2 class="font-extrabold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4 text-emerald-600"></i>
                        <span><?= isPelanggan() ? 'Keranjang Belanja Anda' : 'Keranjang Kasir POS' ?></span>
                    </h2>
                    <?php if (!empty($_SESSION['keranjang'])): ?>
                        <a href="?kosongkan=1" onclick="return confirm('Kosongkan semua isi keranjang?')" class="text-[11px] text-rose-600 hover:underline font-bold">
                            Kosongkan
                        </a>
                    <?php endif; ?>
                </div>

                <?php 
                $grand_total = 0;
                if (empty($_SESSION['keranjang'])): ?>
                    <div class="text-center py-10 text-slate-400">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-2 text-slate-300">
                            <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                        </div>
                        <p class="text-xs font-semibold">Keranjang belanja masih kosong.</p>
                        <a href="kategori.php" class="inline-block mt-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs px-3.5 py-1.5 rounded-xl border border-indigo-200 transition">
                            Pilih Produk Sekarang →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        <?php foreach ($_SESSION['keranjang'] as $item): 
                            $grand_total += $item['subtotal'];
                        ?>
                        <div class="flex justify-between items-center bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80 text-xs">
                            <div class="pr-2 truncate">
                                <p class="font-bold text-slate-800 truncate"><?= htmlspecialchars($item['nama_produk']) ?></p>
                                <p class="text-[11px] text-slate-500 font-medium"><?= $item['jumlah'] ?> x <?= rupiah($item['harga']) ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-black text-indigo-700"><?= rupiah($item['subtotal']) ?></p>
                                <a href="?hapus_item=<?= $item['id_produk'] ?>" class="text-[10px] text-rose-500 hover:text-rose-700 font-bold hover:underline">Hapus</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['keranjang'])): ?>
                <form method="POST" class="pt-3 border-t border-slate-100 space-y-3 text-xs">
                    <div class="bg-slate-900 text-white p-3.5 rounded-2xl flex justify-between items-center shadow-inner">
                        <span class="text-xs text-slate-300 font-medium">Subtotal (`HitungTotal`):</span>
                        <span class="text-base font-black text-emerald-400"><?= rupiah($grand_total) ?></span>
                    </div>

                    <?php if (isPelanggan()): ?>
                        <!-- Identitas Pelanggan Otomatis Terkunci untuk Akun Pelanggan -->
                        <input type="hidden" name="id_pelanggan" value="<?= $id_pel_login ?>">
                        <div class="bg-indigo-50/70 border border-indigo-200 p-2.5 rounded-2xl">
                            <span class="text-[10px] text-indigo-500 font-bold uppercase block">Nama Pemesan:</span>
                            <span class="text-xs font-black text-indigo-900"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?> (Akun Anda)</span>
                        </div>
                    <?php else: ?>
                        <!-- Pilihan Pelanggan Kasir POS -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Pilih Pelanggan Kasir</label>
                            <select name="id_pelanggan" required class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <?php while ($pel = mysqli_fetch_assoc($q_pelanggan)): ?>
                                    <option value="<?= $pel['id_pelanggan'] ?>">
                                        <?= htmlspecialchars($pel['nama_pelanggan']) ?> (<?= htmlspecialchars($pel['kota']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Diskon Transaksi (%)</label>
                        <input type="number" name="diskon_persen" value="0" min="0" max="100" 
                            class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <button type="submit" name="checkout_keranjang" 
                        class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold py-3 rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 active:scale-[0.98]">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i> Checkout Sekarang (TCL COMMIT)
                    </button>
                </form>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>