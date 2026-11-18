<?php
require_once 'functions.php';
cekLogin();

$notif_sukses = "";
$notif_error  = "";

// 1. Tambah Item ke Keranjang Session (Pelanggan & Kasir) dengan Pengecekan Stok Ketat
if (isset($_POST['masuk_keranjang'])) {
    if (!isset($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = [];
    }
    $id_produk = (int)$_POST['id_produk'];
    $jumlah    = max(1, (int)$_POST['jumlah']);

    $q_cek = mysqli_query($conn, "SELECT *, HitungTotal(harga, $jumlah) AS subtotal FROM tbl_produk WHERE id_produk = $id_produk");
    $prod  = mysqli_fetch_assoc($q_cek);

    if ($prod) {
        $current_in_cart = isset($_SESSION['keranjang'][$id_produk]) ? (int)$_SESSION['keranjang'][$id_produk]['jumlah'] : 0;
        $total_diminta   = $current_in_cart + $jumlah;

        if ($prod['stok'] <= 0) {
            $notif_error = "Maaf, stok produk '{$prod['nama_produk']}' saat ini sudah habis!";
        } elseif ($total_diminta > $prod['stok']) {
            $sisa_bisa = max(0, $prod['stok'] - $current_in_cart);
            $notif_error = "Tidak bisa menambah barang! Stok '{$prod['nama_produk']}' tersisa {$prod['stok']} unit. " .
                           ($current_in_cart > 0 ? "(Sudah ada $current_in_cart unit di keranjang, maksimal hanya bisa tambah $sisa_bisa unit lagi)." : "");
        } else {
            if (isset($_SESSION['keranjang'][$id_produk])) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] = $total_diminta;
                $res_sub  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT HitungTotal({$prod['harga']}, $total_diminta) AS sub"));
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
            $notif_sukses = "{$prod['nama_produk']} ($jumlah unit) berhasil dimasukkan ke Keranjang Belanja!";
        }
    }
}

// 2. Tambah Kategori Baru (Khusus Admin / Kasir)
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

// 3. Hapus Kategori (Khusus Administrator)
if (isset($_GET['hapus_kat'])) {
    if (!isAdmin()) {
        $notif_error = "Akses Ditolak: Hanya Administrator yang berhak menghapus data kategori! Kasir tidak diizinkan menghapus.";
    } else {
        $id_kat = (int)$_GET['hapus_kat'];
        if (mysqli_query($conn, "DELETE FROM tbl_kategori WHERE id_kategori = $id_kat")) {
            $notif_sukses = "Kategori berhasil dihapus oleh Administrator!";
        } else {
            $notif_error = "Kategori tidak bisa dihapus karena masih digunakan oleh produk.";
        }
    }
}

// 4. Tambah Produk Baru (Khusus Admin / Kasir)
if (isset($_POST['tambah_produk'])) {
    if (isPelanggan()) {
        $notif_error = "Akses Ditolak: Akun Pelanggan tidak diizinkan menambah produk baru!";
    } else {
        $id_kat  = (int)$_POST['id_kategori'];
        $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
        $harga   = (float)$_POST['harga'];
        $stok    = (int)$_POST['stok'];
        $barcode = isset($_POST['barcode']) ? mysqli_real_escape_string($conn, trim($_POST['barcode'])) : '';
        $foto    = isset($_POST['foto_url']) ? mysqli_real_escape_string($conn, trim($_POST['foto_url'])) : '';
        $badge   = isset($_POST['badge']) ? mysqli_real_escape_string($conn, trim($_POST['badge'])) : 'Mall | ORI';
        $promo   = isset($_POST['promo']) ? mysqli_real_escape_string($conn, trim($_POST['promo'])) : 'PROMO XTRA';

        if (empty($barcode)) {
            $barcode = '899' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        }
        if (empty($foto)) {
            $foto = getFotoProduk($nama);
        }

        if (mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, barcode, foto_url, badge, promo, nama_produk, harga, stok) VALUES ($id_kat, '$barcode', '$foto', '$badge', '$promo', '$nama', $harga, $stok)")) {
            $notif_sukses = "Produk '$nama' berhasil ditambahkan ke katalog!";
        } else {
            $notif_error = "Gagal menambah produk: " . mysqli_error($conn);
        }
    }
}

// 5. Update Tambah Stok (Khusus Admin / Kasir)
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

// Ambil daftar kategori
$q_kategori = mysqli_query($conn, "
    SELECT k.*, COUNT(p.id_produk) AS total_produk, COALESCE(SUM(p.stok), 0) AS total_stok
    FROM tbl_kategori k
    LEFT JOIN tbl_produk p ON k.id_kategori = p.id_kategori
    GROUP BY k.id_kategori
    ORDER BY k.nama_kategori ASC
");

// Ambil daftar produk
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
    <title>Katalog Produk Mall & E-Commerce - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-100/70 text-slate-800 font-sans min-h-screen selection:bg-[#ee4d2d] selection:text-white pb-16">

    <!-- Navbar Global Standar TokoKita -->
    <?php include 'navbar.php'; ?>

    <!-- Sub-Navbar Tab Kategori & Promo dengan Tombol Geser Scroll Kiri & Kanan -->
    <div class="bg-white border-b border-slate-200/90 shadow-sm sticky top-[69px] z-40">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-1.5 py-1.5">
                <!-- Tombol Scroll Sub-Nav Kiri -->
                <button type="button" onclick="scrollSubNav('left')" class="p-1.5 text-slate-500 hover:text-[#ee4d2d] bg-slate-100 hover:bg-rose-50 border border-slate-300 rounded-lg shrink-0 transition active:scale-75 cursor-pointer z-10 shadow-sm" title="Pindah tab promo ke kiri">
                    <i data-lucide="chevron-left" class="w-4 h-4 pointer-events-none"></i>
                </button>

                <!-- Kontainer Tab Promo -->
                <div id="containerSubNavPromo" class="flex-1 min-w-0 flex items-center gap-1 sm:gap-2 overflow-x-auto scroll-smooth no-scrollbar py-0.5 text-[11px] sm:text-xs font-semibold whitespace-nowrap text-slate-600">
                    <button type="button" onclick="pilihTabVoucher(this)" class="tab-promo px-3 py-1.5 rounded-lg font-bold text-[#ee4d2d] bg-rose-50 border border-rose-200/80 transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#ee4d2d]"></span>
                        <span>Voucher Spesial</span>
                    </button>
                    <button type="button" onclick="pilihFilterPromo('0%', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Cicilan Pasti 0%
                    </button>
                    <button type="button" onclick="pilihTabBelanjaKategori(this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Belanja Bds. Kategori
                    </button>
                    <button type="button" onclick="pilihFilterPromo('cicilan', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Cicilan Ringan
                    </button>
                    <button type="button" onclick="pilihFilterPromo('xtra', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Koleksi Pilihan
                    </button>
                    <button type="button" onclick="pilihFilterPromo('level', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        TokoKita Level
                    </button>
                    <button type="button" onclick="pilihFilterPromo('digital', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Belanja Produk Digital
                    </button>
                    <button type="button" onclick="pilihFilterPromo('qris', this)" class="tab-promo px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Bayar QRIS
                    </button>
                </div>

                <!-- Tombol Scroll Sub-Nav Kanan -->
                <button type="button" onclick="scrollSubNav('right')" class="p-1.5 text-slate-500 hover:text-[#ee4d2d] bg-slate-100 hover:bg-rose-50 border border-slate-300 rounded-lg shrink-0 transition active:scale-75 cursor-pointer z-10 shadow-sm" title="Pindah tab promo ke kanan">
                    <i data-lucide="chevron-right" class="w-4 h-4 pointer-events-none"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        function scrollSubNav(direction) {
            const tabs = Array.from(document.querySelectorAll('#containerSubNavPromo .tab-promo'));
            if (tabs.length === 0) return;

            let activeIdx = tabs.findIndex(t => t.classList.contains('font-bold') || t.classList.contains('bg-rose-50'));
            if (activeIdx === -1) activeIdx = 0;

            let targetIdx;
            if (direction === 'left') {
                targetIdx = (activeIdx - 1 + tabs.length) % tabs.length;
            } else {
                targetIdx = (activeIdx + 1) % tabs.length;
            }

            const targetTab = tabs[targetIdx];
            if (targetTab) {
                targetTab.click();
                targetTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }
    </script>

    <main class="max-w-7xl mx-auto p-3 sm:p-5 space-y-4 sm:space-y-6">

        <!-- Notifikasi Sukses / Error -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs font-bold shadow-sm animate-fade-in">
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                    </div>
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="keranjang.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Buka Keranjang (<?= $jml_keranjang ?>) →
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2.5 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <!-- Search Bar & Category Filter Pills -->
        <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="relative flex-1">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariKatalogInput" onkeyup="filterSemuaProduk()" placeholder="Cari nama barang, merk (Motorola, Adidas, Sharp, Samyung...), atau barcode..." 
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-[#ee4d2d] focus:bg-white transition">
                </div>

                <div class="flex items-center gap-2">
                    <a href="keranjang.php" class="bg-[#ee4d2d] hover:bg-[#d73211] text-white px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-md shadow-[#ee4d2d]/25 shrink-0 active:scale-95">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                        <span>Keranjang (<?= $jml_keranjang ?>)</span>
                    </a>

                    <?php if (!isPelanggan()): ?>
                    <button type="button" onclick="toggleFormAdmin()" class="bg-slate-900 hover:bg-slate-800 text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Kelola SKU</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chips Kategori Horizontal -->
            <div id="containerKategoriChips" class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-1 text-[11px] font-bold">
                <button type="button" onclick="pilihKategoriChip('semua', this)" class="chip-kat px-3 py-1 rounded-full bg-[#ee4d2d] text-white shadow-sm shrink-0 transition">
                    Semua Produk
                </button>
                <?php 
                mysqli_data_seek($q_kategori, 0);
                while ($k = mysqli_fetch_assoc($q_kategori)): ?>
                <button type="button" onclick="pilihKategoriChip('<?= strtolower($k['nama_kategori']) ?>', this)" 
                    class="chip-kat px-3 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 shrink-0 transition">
                    <?= htmlspecialchars($k['nama_kategori']) ?> (<?= $k['total_produk'] ?>)
                </button>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Panel Kelola SKU & Kategori (Hanya Terbuka untuk Kasir & Admin) -->
        <?php if (!isPelanggan()): ?>
        <div id="panelAdminKatalog" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Form Tambah Produk Baru -->
            <form method="POST" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 space-y-3">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="font-black text-xs text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i> Tambah Produk SKU Baru
                    </h3>
                    <span class="text-[9px] font-black bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">Admin / Kasir</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <input type="text" name="nama_produk" placeholder="Nama Produk" required class="border p-2 rounded-xl col-span-2">
                    <select name="id_kategori" required class="border p-2 rounded-xl">
                        <option value="">-- Kategori --</option>
                        <?php 
                        mysqli_data_seek($q_kategori, 0);
                        while ($k = mysqli_fetch_assoc($q_kategori)): ?>
                            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="barcode" placeholder="Barcode (opsional)" class="border p-2 rounded-xl font-mono">
                    <input type="number" name="harga" placeholder="Harga (Rp)" min="100" required class="border p-2 rounded-xl">
                    <input type="number" name="stok" placeholder="Stok" min="1" required class="border p-2 rounded-xl">
                    <input type="text" name="foto_url" placeholder="URL Foto Gambar (opsional)" class="border p-2 rounded-xl col-span-2">
                </div>
                <button type="submit" name="tambah_produk" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-2.5 rounded-xl text-xs transition">
                    Simpan Produk ke Katalog
                </button>
            </form>

            <!-- Form Tambah Kategori Baru -->
            <form method="POST" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-200 space-y-3">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="font-black text-xs text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="folder-plus" class="w-4 h-4 text-indigo-600"></i> Tambah Kategori Baru
                    </h3>
                    <span class="text-[9px] font-black bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">Admin / Kasir</span>
                </div>
                <input type="text" name="nama_kategori" placeholder="Nama Kategori (mis: Elektronik Dapur, Otomotif...)" required class="w-full border p-2.5 rounded-xl text-xs font-semibold">
                <button type="submit" name="tambah_kategori" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-2.5 rounded-xl text-xs transition">
                    Simpan Kategori Baru
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- GRID PRODUK MARKETPLACE 6-KOLOM (PERSIS GAMBAR SHOPEE MALL / SPAYLATER) -->
        <div id="gridKatalogProduk" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5 sm:gap-3.5">
            <?php 
            mysqli_data_seek($q_produk, 0);
            while ($pr = mysqli_fetch_assoc($q_produk)): 
                $foto_prod = !empty($pr['foto_url']) ? $pr['foto_url'] : getFotoProduk($pr['nama_produk']);
                $badge_prod = !empty($pr['badge']) ? $pr['badge'] : 'Mall | ORI';
                $promo_prod = !empty($pr['promo']) ? $pr['promo'] : 'PROMO XTRA';
                $is_habis   = $pr['stok'] <= 0;
                $jumlah_di_keranjang = isset($_SESSION['keranjang'][$pr['id_produk']]) ? (int)$_SESSION['keranjang'][$pr['id_produk']]['jumlah'] : 0;
                $sisa_dapat_ditambah = max(0, (int)$pr['stok'] - $jumlah_di_keranjang);
            ?>
            <div class="card-produk group bg-white rounded-xl border border-slate-200/90 hover:border-[#ee4d2d]/60 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-200 flex flex-col justify-between overflow-hidden relative"
                 data-nama="<?= strtolower($pr['nama_produk']) ?>"
                 data-kategori="<?= strtolower($pr['nama_kategori'] ?? '') ?>"
                 data-barcode="<?= strtolower($pr['barcode'] ?? '') ?>"
                 data-promo="<?= strtolower($promo_prod) ?>">

                <!-- Kontainer Foto Produk (Aspek Rasio 1:1 Persegi Bersih Persis Gambar) -->
                <div class="relative aspect-square w-full bg-white overflow-hidden flex items-center justify-center p-2.5 sm:p-3 border-b border-slate-100">
                    <img src="<?= htmlspecialchars($foto_prod) ?>" 
                         alt="<?= htmlspecialchars($pr['nama_produk']) ?>"
                         loading="lazy"
                         onerror="this.src='https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500&auto=format&fit=crop&q=80'"
                         class="w-full h-full object-contain group-hover:scale-105 transition duration-300">

                    <!-- Overlay Badge Kuning Bawah-Kiri (PROMO XTRA / CICILAN 0%) -->
                    <?php if (!empty($promo_prod)): ?>
                    <div class="absolute bottom-1.5 left-1.5 flex items-center gap-1 pointer-events-none">
                        <span class="bg-[#ffd428] text-amber-950 font-black text-[8.5px] px-1.5 py-0.5 rounded shadow-sm tracking-tight leading-none uppercase">
                            <?= htmlspecialchars($promo_prod) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Overlay Tag Keaslian Atas-Kanan -->
                    <div class="absolute top-1.5 right-1.5 pointer-events-none">
                        <span class="bg-[#ee4d2d]/90 text-white font-black text-[8px] px-1 py-0.5 rounded shadow-sm tracking-wider uppercase">
                            ORIGINAL
                        </span>
                    </div>
                </div>

                <!-- Kontainer Deskripsi & Harga -->
                <div class="p-2 sm:p-2.5 flex flex-col flex-1 justify-between gap-1.5">
                    <div>
                        <!-- Judul Produk dengan Badge Mall | ORI Merah -->
                        <h3 class="text-[11.5px] sm:text-xs font-semibold text-slate-800 line-clamp-2 leading-snug group-hover:text-[#ee4d2d] transition" 
                            title="<?= htmlspecialchars($pr['nama_produk']) ?>">
                            <span class="inline-block bg-[#ee4d2d] text-white font-black text-[8px] px-1 py-0.2 rounded mr-1 align-baseline tracking-wider uppercase">
                                <?= htmlspecialchars($badge_prod) ?>
                            </span>
                            <?= htmlspecialchars($pr['nama_produk']) ?>
                        </h3>
                    </div>

                    <div class="pt-1 border-t border-slate-100 flex flex-col gap-1">
                        <!-- Harga Merah khas Shopee / E-Commerce -->
                        <div class="flex items-baseline gap-0.5 text-[#ee4d2d]">
                            <span class="text-[10px] font-bold">Rp</span>
                            <span class="text-sm sm:text-base font-black tracking-tight leading-none"><?= number_format($pr['harga'], 0, ',', '.') ?></span>
                        </div>

                        <!-- Barcode & Stok -->
                        <div class="flex items-center justify-between text-[9.5px] text-slate-400 font-medium">
                            <span class="truncate max-w-[80px]"><?= htmlspecialchars($pr['nama_kategori'] ?? 'Umum') ?></span>
                            <span class="<?= $is_habis ? 'text-rose-500 font-bold' : ($pr['stok'] <= 10 ? 'text-amber-600 font-bold' : 'text-slate-500') ?>">
                                <?= $is_habis ? 'Habis' : 'Stok ' . $pr['stok'] ?>
                            </span>
                        </div>

                        <!-- Tombol Beli / Masuk Keranjang Express -->
                        <?php if ($is_habis || $sisa_dapat_ditambah <= 0): ?>
                            <button type="button" disabled class="w-full bg-slate-200 text-slate-400 text-[10.5px] font-bold py-1.5 rounded-lg cursor-not-allowed">
                                <?= $is_habis ? 'Stok Habis' : 'Batas di Keranjang' ?>
                            </button>
                        <?php else: ?>
                            <form method="POST" class="mt-0.5">
                                <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                                <div class="flex items-center gap-1">
                                    <input type="number" name="jumlah" value="1" min="1" max="<?= $sisa_dapat_ditambah ?>" required
                                        aria-label="Jumlah <?= htmlspecialchars($pr['nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="w-12 border border-slate-200 rounded-lg p-1.5 text-xs text-center font-bold focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                                    <button type="submit" name="masuk_keranjang"
                                        class="flex-1 bg-[#ee4d2d] hover:bg-[#d73211] active:scale-95 text-white font-black text-[10.5px] py-1.5 rounded-lg transition flex items-center justify-center gap-1 shadow-sm shadow-[#ee4d2d]/25">
                                        <i data-lucide="shopping-cart" class="w-3 h-3"></i>
                                        <span>+ Keranjang</span>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endwhile; ?>
        </div>

        <!-- Notifikasi jika tidak ada produk yang cocok dengan pencarian -->
        <div id="pesanTidakAdaProduk" class="hidden py-12 text-center bg-white rounded-2xl border border-slate-200">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mx-auto mb-2">
                <i data-lucide="search-x" class="w-6 h-6"></i>
            </div>
            <h3 class="text-sm font-black text-slate-800">Produk Tidak Ditemukan</h3>
            <p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci pencarian lain atau pilih kategori Semua Produk.</p>
        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        function toggleFormAdmin() {
            const panel = document.getElementById('panelAdminKatalog');
            if (panel) {
                panel.classList.toggle('hidden');
            }
        }

        let filterKatAktif = 'semua';
        let filterPromoAktif = '';

        function aktifkanTabPromo(btn) {
            document.querySelectorAll('.tab-promo').forEach(b => {
                b.classList.remove('text-[#ee4d2d]', 'bg-rose-50', 'border', 'border-rose-200/80', 'font-bold');
            });
            btn.classList.add('text-[#ee4d2d]', 'bg-rose-50', 'border', 'border-rose-200/80', 'font-bold');
        }

        function pilihTabVoucher(btn) {
            aktifkanTabPromo(btn);
            filterPromoAktif = '';
            filterSemuaProduk();
        }

        function pilihTabBelanjaKategori(btn) {
            aktifkanTabPromo(btn);
            scrollKeKategori();
        }

        function pilihFilterPromo(tipe, btn) {
            aktifkanTabPromo(btn);
            filterPromoAktif = tipe === 'semua' ? '' : tipe.toLowerCase();
            filterSemuaProduk();
        }

        function pilihFilterKategori(kat, btn) {
            filterKatAktif = 'semua';
            filterPromoAktif = '';
            document.querySelectorAll('.chip-kat').forEach(c => {
                c.classList.remove('bg-[#ee4d2d]', 'text-white');
                c.classList.add('bg-slate-100', 'text-slate-700');
            });
            const firstChip = document.querySelector('.chip-kat');
            if (firstChip) {
                firstChip.classList.remove('bg-slate-100', 'text-slate-700');
                firstChip.classList.add('bg-[#ee4d2d]', 'text-white');
            }
            filterSemuaProduk();
        }

        function scrollKeKategori() {
            const el = document.getElementById('containerKategoriChips');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function pilihKategoriChip(kat, btn) {
            filterKatAktif = kat.toLowerCase();
            document.querySelectorAll('.chip-kat').forEach(c => {
                c.classList.remove('bg-[#ee4d2d]', 'text-white');
                c.classList.add('bg-slate-100', 'text-slate-700');
            });
            btn.classList.remove('bg-slate-100', 'text-slate-700');
            btn.classList.add('bg-[#ee4d2d]', 'text-white');
            filterSemuaProduk();
        }

        function filterSemuaProduk() {
            const query = (document.getElementById('cariKatalogInput')?.value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.card-produk');
            let adaProduk = 0;

            cards.forEach(card => {
                const nama = card.getAttribute('data-nama') || '';
                const kat  = card.getAttribute('data-kategori') || '';
                const bc   = card.getAttribute('data-barcode') || '';
                const prm  = card.getAttribute('data-promo') || '';

                const cocokQuery = query === '' || nama.includes(query) || kat.includes(query) || bc.includes(query);
                const cocokKat   = filterKatAktif === 'semua' || kat.includes(filterKatAktif);
                const cocokPromo = filterPromoAktif === '' || prm.includes(filterPromoAktif);

                if (cocokQuery && cocokKat && cocokPromo) {
                    card.style.display = 'flex';
                    adaProduk++;
                } else {
                    card.style.display = 'none';
                }
            });

            const pesanKosong = document.getElementById('pesanTidakAdaProduk');
            if (pesanKosong) {
                pesanKosong.classList.toggle('hidden', adaProduk > 0);
            }
        }
    </script>
</body>
</html>
