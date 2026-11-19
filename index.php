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

// 1. Tambah Item ke Keranjang Langsung dari Dashboard Belanja
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
            $notif_sukses = "{$prod['nama_produk']} ($jumlah unit) berhasil ditambahkan ke Keranjang Belanja!";
        }
    }
}

// 2. Tambah Pelanggan via Stored Procedure (Khusus Admin/Kasir)
if (isset($_POST['tambah_pelanggan']) && !isPelanggan()) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_pelanggan']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);

    if (mysqli_query($conn, "CALL TambahPelanggan('$nama', '$kota')")) {
        $notif_sukses = "Pelanggan '$nama' berhasil ditambahkan melalui Stored Procedure!";
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
    } else {
        $notif_error = "Gagal: " . mysqli_error($conn);
    }
}

// 3. Transaksi Baru via Stored Procedure (Khusus Admin/Kasir)
if (isset($_POST['tambah_transaksi']) && !isPelanggan()) {
    $id_pelanggan = (int)$_POST['id_pelanggan'];
    $id_produk    = (int)$_POST['id_produk'];
    $jumlah       = (int)$_POST['jumlah'];
    $diskon       = (float)$_POST['diskon'];

    if (mysqli_query($conn, "CALL sp_TambahTransaksiBaru($id_pelanggan, $id_produk, $jumlah, $diskon)")) {
        $notif_sukses = "Transaksi berhasil! Function HitungTotal & HitungDiskon dijalankan, stok otomatis berkurang.";
        while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
        $id_transaksi_baru = (int)mysqli_insert_id($conn);
        if ($id_transaksi_baru > 0 && !mysqli_query($conn, "UPDATE tbl_transaksi SET waktu_transaksi = NOW() WHERE id_transaksi = $id_transaksi_baru AND waktu_transaksi IS NULL")) {
            $notif_error = "Transaksi tersimpan, tetapi waktu transaksi gagal dicatat: " . mysqli_error($conn);
            $notif_sukses = "";
        }
    } else {
        $notif_error = "Gagal: " . mysqli_error($conn);
    }
}

// Pemeriksaan Role Pengguna
$is_role_pelanggan = isPelanggan();
$nama_user_login   = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'User';

// Data Personal Khusus Akun Pelanggan
$id_pel_saya = 0;
$stat_saya = ['total_trx' => 0, 'total_belanja' => 0];
$q_riwayat_saya = null;
$data_pel_saya = null;

if ($is_role_pelanggan) {
    $id_pel_saya = getIdPelangganLogin();

    // 4. Update / Simpan Alamat Pengiriman Sendiri oleh Pelanggan Sebelum Membuat Pesanan
    if (isset($_POST['simpan_alamat_pelanggan']) && $id_pel_saya > 0) {
        $alamat_baru = mysqli_real_escape_string($conn, trim($_POST['alamat']));
        $telp_baru   = mysqli_real_escape_string($conn, trim($_POST['no_telepon']));
        $kota_baru   = mysqli_real_escape_string($conn, trim($_POST['kota']));

        if (!empty($alamat_baru)) {
            $up_addr = mysqli_query($conn, "UPDATE tbl_pelanggan SET alamat = '$alamat_baru', no_telepon = '$telp_baru', kota = '$kota_baru' WHERE id_pelanggan = $id_pel_saya");
            if ($up_addr) {
                $notif_sukses = "Alamat pengiriman Anda berhasil disimpan! Pesanan yang Anda buat akan otomatis dikirimkan ke alamat ini.";
            } else {
                $notif_error = "Gagal memperbarui alamat: " . mysqli_error($conn);
            }
        } else {
            $notif_error = "Alamat pengiriman tidak boleh kosong!";
        }
    }

    $saldo_member_saya = 0;
    if ($id_pel_saya > 0) {
        $q_sum_saya = mysqli_query($conn, "SELECT COUNT(*) AS total_trx, COALESCE(SUM(total_belanja),0) AS total_belanja FROM tbl_transaksi WHERE id_pelanggan = $id_pel_saya");
        if ($r_ss = mysqli_fetch_assoc($q_sum_saya)) {
            $stat_saya = $r_ss;
        }
        $q_sal_saya = mysqli_query($conn, "SELECT COALESCE(SUM(nominal), 0) AS total_saldo FROM tbl_topup WHERE id_pelanggan = $id_pel_saya");
        if ($r_sl = mysqli_fetch_assoc($q_sal_saya)) {
            $saldo_member_saya = (float)$r_sl['total_saldo'];
        }
        $q_riwayat_saya = mysqli_query($conn, "SELECT * FROM tbl_transaksi WHERE id_pelanggan = $id_pel_saya ORDER BY id_transaksi DESC LIMIT 5");

        $qp_detail = mysqli_query($conn, "SELECT * FROM tbl_pelanggan WHERE id_pelanggan = $id_pel_saya LIMIT 1");
        if ($qp_detail && mysqli_num_rows($qp_detail) > 0) {
            $data_pel_saya = mysqli_fetch_assoc($qp_detail);
        }
    }
}

// Statistik Global Toko (Hanya untuk Admin/Kasir)
$stat_omzet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_belanja),0) AS omzet, COUNT(*) AS jml_trx FROM tbl_transaksi"));
$stat_pel   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml_pel FROM tbl_pelanggan"));
$stat_prod  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(stok),0) AS total_stok FROM tbl_produk"));

// Query Rekapitulasi Member (Hanya untuk Admin/Kasir)
$q_rekap = null;
if (!$is_role_pelanggan) {
    $q_rekap = mysqli_query($conn, "
        SELECT id_pelanggan, nama_pelanggan, total_transaksi, total_belanja,
               HitungPajak(total_belanja) AS estimasi_ppn,
               KategoriBelanja(total_belanja) AS kategori
        FROM view_total_belanja
    ");
}

// Query Produk Katalog Mall untuk Grid Rekomendasi
$q_katalog_mall = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM tbl_produk p 
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori 
    ORDER BY p.id_produk DESC 
    LIMIT 24
");

$q_produk_opt = mysqli_query($conn, "SELECT * FROM tbl_produk");
$jml_keranjang_aktif = isset($_SESSION['keranjang']) ? count($_SESSION['keranjang']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Belanja - TokoKita Smart Mall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-100/70 text-slate-800 font-sans min-h-screen selection:bg-[#ee4d2d] selection:text-white pb-16">

    <!-- Global TokoKita Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Sub-Navbar Tab Kategori & Promo dengan Tombol Geser Scroll Kiri & Kanan -->
    <div class="bg-white border-b border-slate-200/90 shadow-sm sticky top-[69px] z-40">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center gap-1.5 py-1.5">
                <!-- Tombol Scroll Sub-Nav Kiri -->
                <button type="button" onclick="scrollSubNav('left')" class="p-1.5 text-slate-500 hover:text-[#ee4d2d] bg-slate-100 hover:bg-rose-50 border border-slate-300 rounded-lg shrink-0 transition active:scale-75 cursor-pointer z-10 shadow-sm" title="Pindah tab promo ke kiri">
                    <i data-lucide="chevron-left" class="w-4 h-4 pointer-events-none"></i>
                </button>

                <!-- Kontainer Tab Promo Shopee SPayLater -->
                <div id="containerSubNavPromo" class="flex-1 min-w-0 flex items-center gap-1 sm:gap-2 overflow-x-auto scroll-smooth no-scrollbar py-0.5 text-[11px] sm:text-xs font-semibold whitespace-nowrap text-slate-600">
                    <button type="button" onclick="pilihFilterHomePromo('', this)" class="tab-promo-item px-3 py-1.5 rounded-lg font-bold text-[#ee4d2d] bg-rose-50 border border-rose-200/80 transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span class="dot-aktif w-1.5 h-1.5 rounded-full bg-[#ee4d2d] shrink-0"></span>
                        <span>Voucher Spesial SPayLater</span>
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('0%', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Cicilan Pasti 0%
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('kategori', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Belanja Bds. Kategori
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('cicilan', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Cicilan Ringan
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('xtra', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Koleksi Pilihan
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('level', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        SPayLater Level
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('digital', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        Belanja Produk Digital
                    </button>
                    <button type="button" onclick="pilihFilterHomePromo('qris', this)" class="tab-promo-item px-3 py-1.5 rounded-lg hover:text-[#ee4d2d] hover:bg-slate-50 transition shrink-0 cursor-pointer">
                        SPayLater Bayar QRIS
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
        // Fungsi Pindah Tab & Bergeser Horizontal Halus
        function scrollSubNav(direction) {
            const tabs = Array.from(document.querySelectorAll('#containerSubNavPromo .tab-promo-item'));
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

        // Fungsi Filter Promo & Aktifkan Tab
        function pilihFilterHomePromo(tipe, btn) {
            document.querySelectorAll('.tab-promo-item').forEach(b => {
                b.classList.remove('font-bold', 'text-[#ee4d2d]', 'bg-rose-50', 'border', 'border-rose-200/80');
                b.classList.add('text-slate-600', 'hover:text-[#ee4d2d]', 'hover:bg-slate-50');
                const dot = b.querySelector('.dot-aktif');
                if (dot) dot.remove();
            });

            btn.classList.remove('text-slate-600', 'hover:bg-slate-50');
            btn.classList.add('font-bold', 'text-[#ee4d2d]', 'bg-rose-50', 'border', 'border-rose-200/80');
            if (!btn.querySelector('.dot-aktif')) {
                const dot = document.createElement('span');
                dot.className = 'dot-aktif w-1.5 h-1.5 rounded-full bg-[#ee4d2d] shrink-0';
                btn.insertBefore(dot, btn.firstChild);
            }

            const filterKey = tipe.toLowerCase().trim();
            const cards = document.querySelectorAll('.card-mall-item');

            cards.forEach(card => {
                const promo = (card.getAttribute('data-promo') || '').toLowerCase();
                const kat = (card.getAttribute('data-kategori') || '').toLowerCase();

                let cocok = false;
                if (filterKey === '' || filterKey === 'semua' || filterKey === 'voucher') {
                    cocok = true;
                } else if (filterKey === 'kategori') {
                    cocok = true;
                } else {
                    cocok = promo.includes(filterKey) || kat.includes(filterKey);
                }

                card.style.display = cocok ? 'flex' : 'none';
            });

            if (filterKey === 'kategori') {
                const sec = document.getElementById('sectionKatalogMall');
                if (sec) {
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
    </script>

    <main class="max-w-7xl mx-auto p-3 sm:p-5 space-y-4 sm:space-y-6">

        <!-- Notifikasi -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs font-bold shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                    </div>
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="keranjang.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i> Lihat Keranjang (<?= $jml_keranjang_aktif ?>) →
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2.5 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Disesuaikan Berdasarkan Role -->
        <?php if ($is_role_pelanggan): ?>
        <!-- Stat Cards Khusus Akun Pelanggan -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-0.5">
                    <p class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Akun Pembeli</p>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 group-hover:text-indigo-600 transition truncate max-w-[130px] sm:max-w-[160px]"><?= htmlspecialchars($nama_user_login) ?></h3>
                    <a href="profil.php" class="inline-flex items-center gap-1 text-[10px] text-indigo-600 font-extrabold bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-full transition border border-indigo-200" title="Buka Profil Saya">
                        <i data-lucide="user" class="w-3 h-3"></i>
                        <span>Profil Saya →</span>
                    </a>
                </div>
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/20 shrink-0">
                    <i data-lucide="user-check" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-0.5">
                    <p class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Pesanan Saya</p>
                    <h3 class="text-lg sm:text-2xl font-black text-slate-900 group-hover:text-emerald-600 transition"><?= $stat_saya['total_trx'] ?> <span class="text-xs font-semibold text-slate-400">Order</span></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="receipt" class="w-3 h-3"></i>
                        <span>Riwayat Belanja</span>
                    </div>
                </div>
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 shrink-0">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-0.5">
                    <p class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Saldo Member Saya</p>
                    <h3 class="text-base sm:text-xl font-black text-slate-900 group-hover:text-amber-600 transition"><?= rupiah($saldo_member_saya) ?></h3>
                    <a href="saldo.php" class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-extrabold bg-emerald-50 hover:bg-emerald-100 px-2.5 py-0.5 rounded-full transition border border-emerald-200 shadow-sm">
                        <i data-lucide="plus-circle" class="w-3 h-3 text-emerald-600"></i>
                        <span>Isi Saldo Member →</span>
                    </a>
                </div>
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20 shrink-0">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-0.5">
                    <p class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">Keranjang Belanja</p>
                    <h3 class="text-lg sm:text-2xl font-black text-slate-900 group-hover:text-[#ee4d2d] transition"><?= $jml_keranjang_aktif ?> <span class="text-xs font-semibold text-slate-400">Item</span></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-[#ee4d2d] font-extrabold bg-rose-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="shopping-cart" class="w-3 h-3"></i>
                        <span>Siap Checkout</span>
                    </div>
                </div>
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-tr from-[#ee4d2d] to-orange-500 text-white flex items-center justify-center shadow-md shadow-[#ee4d2d]/25 shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Panel Alamat Pengiriman Pelanggan (Sebelum Membuat Pesanan) -->
        <div class="bg-gradient-to-r from-orange-50 via-white to-rose-50 border border-orange-200/80 p-4 sm:p-5 rounded-3xl shadow-sm space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-orange-100 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-[#ee4d2d] to-amber-500 text-white flex items-center justify-center shadow-md shadow-[#ee4d2d]/20 shrink-0">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-slate-800">Alamat Pengiriman Saya</h3>
                            <span class="text-[9.5px] bg-[#ee4d2d] text-white font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                                <?= !empty($data_pel_saya['alamat']) ? 'Alamat Tersimpan' : 'Belum Diatur' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500">Masukkan alamat pengiriman Anda sendiri sebelum membuat pesanan di TokoKita Mall</p>
                    </div>
                </div>

                <button type="button" onclick="toggleFormAlamatDashboard()" id="btnToggleAlamat"
                    class="bg-white hover:bg-slate-50 text-[#ee4d2d] border border-orange-300 font-extrabold text-xs px-3.5 py-2 rounded-xl transition shadow-sm flex items-center gap-1.5 active:scale-95 cursor-pointer">
                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                    <span><?= !empty($data_pel_saya['alamat']) ? 'Ubah Alamat Pengiriman' : '+ Masukkan Alamat Sendiri' ?></span>
                </button>
            </div>

            <!-- Tampilan Ringkas Alamat Saat Ini -->
            <div id="boxAlamatRingkas" class="<?= empty($data_pel_saya['alamat']) ? 'hidden' : '' ?> bg-white/80 p-3.5 rounded-2xl border border-orange-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="space-y-1 max-w-2xl">
                    <p class="font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="text-indigo-950 font-black text-xs"><?= htmlspecialchars($data_pel_saya['nama_pelanggan'] ?? $nama_user_login) ?></span>
                        <span class="text-slate-300">|</span>
                        <span class="text-slate-600 font-bold"><?= htmlspecialchars($data_pel_saya['no_telepon'] ?? '-') ?></span>
                        <span class="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.2 rounded border border-emerald-200">Alamat Utama</span>
                    </p>
                    <p class="text-slate-700 leading-relaxed font-medium">
                        <?= htmlspecialchars($data_pel_saya['alamat'] ?? 'Belum ada alamat') ?>, <span class="font-bold text-slate-900"><?= htmlspecialchars($data_pel_saya['kota'] ?? '') ?></span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="kategori.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs px-3.5 py-2 rounded-xl transition shadow-md shadow-indigo-600/20 flex items-center gap-1.5">
                        <i data-lucide="store" class="w-3.5 h-3.5"></i> Buka Katalog Produk
                    </a>
                    <a href="keranjang.php" class="bg-[#ee4d2d] hover:bg-[#d73211] text-white font-extrabold text-xs px-4 py-2 rounded-xl transition shadow-md shadow-[#ee4d2d]/25 flex items-center gap-1.5">
                        <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Buat Pesanan
                    </a>
                </div>
            </div>

            <!-- Form Edit / Masukkan Alamat Sendiri -->
            <form method="POST" id="formAlamatDashboard" class="<?= !empty($data_pel_saya['alamat']) ? 'hidden' : '' ?> bg-white p-4.5 rounded-2xl border border-orange-200/90 space-y-3">
                <input type="hidden" name="id_pelanggan" value="<?= $id_pel_saya ?>">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h4 class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="home" class="w-4 h-4 text-[#ee4d2d]"></i>
                        <span>Masukkan Alamat Pengiriman Anda Sendiri</span>
                    </h4>
                    <span class="text-[10px] text-slate-400">Pastikan alamat jelas agar kurir mengantar pesanan tepat waktu</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">No. Handphone / WhatsApp Penerima:</label>
                        <input type="text" name="no_telepon" value="<?= htmlspecialchars($data_pel_saya['no_telepon'] ?? '') ?>" required placeholder="Contoh: 0812-3456-7890"
                            class="w-full border border-slate-300 bg-slate-50 focus:bg-white p-2.5 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Kota / Kabupaten & Provinsi:</label>
                        <input type="text" name="kota" value="<?= htmlspecialchars($data_pel_saya['kota'] ?? 'Ponorogo') ?>" required placeholder="Contoh: Ponorogo, Jawa Timur"
                            class="w-full border border-slate-300 bg-slate-50 focus:bg-white p-2.5 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d]">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Alamat Lengkap (Nama Jalan, No. Rumah, RT/RW, Kelurahan, Kecamatan):</label>
                    <textarea name="alamat" rows="2" required placeholder="Tuliskan nama jalan, nomor rumah, RT/RW, kelurahan, dan kecamatan Anda sendiri..."
                        class="w-full border border-slate-300 bg-slate-50 focus:bg-white p-2.5 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ee4d2d] leading-relaxed"><?= htmlspecialchars($data_pel_saya['alamat'] ?? '') ?></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <?php if (!empty($data_pel_saya['alamat'])): ?>
                    <button type="button" onclick="toggleFormAlamatDashboard()" class="text-xs font-bold text-slate-500 hover:text-slate-800 px-3 py-2 cursor-pointer">
                        Batal
                    </button>
                    <?php endif; ?>
                    <button type="submit" name="simpan_alamat_pelanggan" 
                        class="bg-[#ee4d2d] hover:bg-[#d73211] text-white font-black text-xs px-5 py-2.5 rounded-xl transition shadow-lg shadow-[#ee4d2d]/30 flex items-center gap-1.5 active:scale-95 cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4"></i> Simpan Alamat Pengiriman Saya
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Stat Cards Khusus Administrator & Kasir POS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">Total Penjualan</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 group-hover:text-amber-600 transition"><?= rupiah($stat_omzet['omzet']) ?></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-amber-600 font-extrabold bg-amber-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="trending-up" class="w-3 h-3"></i>
                        <span>Omzet Toko</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/25 shrink-0">
                    <i data-lucide="wallet" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 group-hover:text-indigo-600 transition"><?= $stat_omzet['jml_trx'] ?> <span class="text-xs font-semibold text-slate-400">Order</span></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-indigo-600 font-extrabold bg-indigo-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="receipt" class="w-3 h-3"></i>
                        <span>Order Tercatat</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/25 shrink-0">
                    <i data-lucide="receipt" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">Member Pelanggan</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 group-hover:text-emerald-600 transition"><?= $stat_pel['jml_pel'] ?> <span class="text-xs font-semibold text-slate-400">User</span></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="users" class="w-3 h-3"></i>
                        <span>Aktif Terdaftar</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-lg shadow-emerald-500/25 shrink-0">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">Stok Barang</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 group-hover:text-purple-600 transition"><?= $stat_prod['total_stok'] ?> <span class="text-xs font-semibold text-slate-400">Unit</span></h3>
                    <div class="inline-flex items-center gap-1 text-[10px] text-purple-600 font-extrabold bg-purple-50 px-2 py-0.5 rounded-full">
                        <i data-lucide="package-check" class="w-3 h-3"></i>
                        <span>Inventori Siap</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-purple-600 to-fuchsia-600 text-white flex items-center justify-center shadow-lg shadow-purple-600/25 shrink-0">
                    <i data-lucide="package-check" class="w-6 h-6"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION: PRODUK MALL & REKOMENDASI BELANJA (PERSIS SEPERTI GAMBAR SHOPEE MALL / SPAYLATER) -->
        <div id="sectionKatalogMall" class="space-y-3">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-5 bg-[#ee4d2d] rounded-sm"></span>
                    <h2 class="font-black text-sm sm:text-base text-slate-800 tracking-tight">Katalog Produk TokoKita Mall</h2>
                    <span class="text-[10px] font-extrabold bg-rose-50 text-[#ee4d2d] border border-rose-200 px-2 py-0.5 rounded-full">100% Original</span>
                </div>
                <a href="kategori.php" class="text-xs font-bold text-[#ee4d2d] hover:underline flex items-center gap-1">
                    Lihat Semua Produk →
                </a>
            </div>

            <!-- Grid 6 Kolom E-Commerce Modern Persis Gambar -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5 sm:gap-3.5">
                <?php 
                mysqli_data_seek($q_katalog_mall, 0);
                while ($pr = mysqli_fetch_assoc($q_katalog_mall)): 
                    $foto_prod = !empty($pr['foto_url']) ? $pr['foto_url'] : getFotoProduk($pr['nama_produk']);
                    $badge_prod = !empty($pr['badge']) ? $pr['badge'] : 'Mall | ORI';
                    $promo_prod = !empty($pr['promo']) ? $pr['promo'] : 'PROMO XTRA';
                    $is_habis   = $pr['stok'] <= 0;
                    $jumlah_di_keranjang = isset($_SESSION['keranjang'][$pr['id_produk']]) ? (int)$_SESSION['keranjang'][$pr['id_produk']]['jumlah'] : 0;
                    $sisa_dapat_ditambah = max(0, (int)$pr['stok'] - $jumlah_di_keranjang);
                ?>
                <div class="card-mall-item group bg-white rounded-xl border border-slate-200/90 hover:border-[#ee4d2d]/60 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-200 flex flex-col justify-between overflow-hidden relative"
                     data-promo="<?= strtolower($promo_prod) ?>"
                     data-kategori="<?= strtolower($pr['nama_kategori'] ?? '') ?>">

                    <!-- Foto Produk (Persegi 1:1) -->
                    <div class="relative aspect-square w-full bg-white overflow-hidden flex items-center justify-center p-2.5 sm:p-3 border-b border-slate-100">
                        <img src="<?= htmlspecialchars($foto_prod) ?>" 
                             alt="<?= htmlspecialchars($pr['nama_produk']) ?>"
                             loading="lazy"
                             onerror="this.src='https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500&auto=format&fit=crop&q=80'"
                             class="w-full h-full object-contain group-hover:scale-105 transition duration-300">

                        <!-- Overlay Badge Kuning Bawah-Kiri (PROMO XTRA) -->
                        <?php if (!empty($promo_prod)): ?>
                        <div class="absolute bottom-1.5 left-1.5 flex items-center gap-1 pointer-events-none">
                            <span class="bg-[#ffd428] text-amber-950 font-black text-[8px] sm:text-[8.5px] px-1.5 py-0.5 rounded shadow-sm tracking-tight leading-none uppercase">
                                <?= htmlspecialchars($promo_prod) ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <!-- Tag Keaslian Atas-Kanan -->
                        <div class="absolute top-1.5 right-1.5 pointer-events-none">
                            <span class="bg-[#ee4d2d]/90 text-white font-black text-[8px] px-1 py-0.5 rounded shadow-sm tracking-wider uppercase">
                                ORI
                            </span>
                        </div>
                    </div>

                    <!-- Deskripsi & Harga -->
                    <div class="p-2 sm:p-2.5 flex flex-col flex-1 justify-between gap-1.5">
                        <div>
                            <h3 class="text-[11.5px] sm:text-xs font-semibold text-slate-800 line-clamp-2 leading-snug group-hover:text-[#ee4d2d] transition" 
                                title="<?= htmlspecialchars($pr['nama_produk']) ?>">
                                <span class="inline-block bg-[#ee4d2d] text-white font-black text-[8px] px-1 py-0.2 rounded mr-1 align-baseline tracking-wider uppercase">
                                    <?= htmlspecialchars($badge_prod) ?>
                                </span>
                                <?= htmlspecialchars($pr['nama_produk']) ?>
                            </h3>
                        </div>

                        <div class="pt-1 border-t border-slate-100 flex flex-col gap-1">
                            <div class="flex items-baseline gap-0.5 text-[#ee4d2d]">
                                <span class="text-[10px] font-bold">Rp</span>
                                <span class="text-sm sm:text-base font-black tracking-tight leading-none"><?= number_format($pr['harga'], 0, ',', '.') ?></span>
                            </div>

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
        </div>

        <?php if ($is_role_pelanggan): ?>
        <!-- Riwayat Pesanan Belanja Saya (Khusus Akun Pelanggan, TANPA Status Member Orang Lain) -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 space-y-3">
            <div class="border-b border-slate-100 pb-2.5 flex items-center justify-between">
                <div>
                    <h2 class="font-black text-xs sm:text-sm text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-4 h-4 text-[#ee4d2d]"></i>
                        Pesanan Belanja Terakhir Saya
                    </h2>
                    <p class="text-[10.5px] text-slate-400">Riwayat transaksi belanja pribadi akun <?= htmlspecialchars($nama_user_login) ?></p>
                </div>
                <a href="pesanan_saya.php" class="text-[10px] font-extrabold text-indigo-600 hover:underline">Lihat Semua Riwayat →</a>
            </div>

            <?php if ($q_riwayat_saya && mysqli_num_rows($q_riwayat_saya) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-200">
                            <th class="p-3">No Transaksi</th>
                            <th class="p-3">Tanggal & Jam</th>
                            <th class="p-3">Total Pembayaran</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($trx = mysqli_fetch_assoc($q_riwayat_saya)): ?>
                        <?php $status_trx_tampilan = statusPembayaranTampilan($trx['status_pembayaran'] ?? ''); ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3 font-mono font-bold text-indigo-600">#TRX-<?= str_pad($trx['id_transaksi'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="p-3 font-medium text-slate-600">
                                <?= htmlspecialchars($trx['tanggal']) ?>
                                <span class="block text-[10px] text-slate-400"><?= !empty($trx['waktu_transaksi']) ? date('H:i:s', strtotime($trx['waktu_transaksi'])) : 'Jam belum tercatat' ?></span>
                            </td>
                            <td class="p-3 font-black text-emerald-600"><?= rupiah($trx['total_belanja']) ?></td>
                            <td class="p-3 text-center">
                                <span class="px-2.5 py-0.5 text-[10px] rounded-full font-black <?= $status_trx_tampilan['class'] ?>">
                                    <?= htmlspecialchars($status_trx_tampilan['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="py-6 text-center text-xs text-slate-400">
                <p>Belum ada riwayat pesanan belanja. Silakan pilih produk dari katalog di atas!</p>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Form Stored Procedure Khusus Administrator & Kasir POS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <form method="POST" class="bg-white p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="user-plus" class="w-5 h-5 text-indigo-600"></i>
                        <h2 class="font-black text-sm text-slate-800">Tambah Pelanggan Baru</h2>
                    </div>
                    <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md">Stored Procedure</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" name="nama_pelanggan" placeholder="Nama Pelanggan" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                    <input type="text" name="kota" placeholder="Kota (mis: Ponorogo)" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" name="tambah_pelanggan" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black py-3 px-4 rounded-2xl transition shadow-lg shadow-indigo-600/25 active:scale-95">
                    Simpan Pelanggan Baru (`CALL TambahPelanggan`)
                </button>
            </form>

            <form method="POST" class="bg-white p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-5 h-5 text-emerald-600"></i>
                        <h2 class="font-black text-sm text-slate-800">Transaksi Kasir Cepat</h2>
                    </div>
                    <span class="text-[10px] font-extrabold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-md">Trigger Auto-Stok</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <select name="id_pelanggan" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                        <?php 
                        $q_pel = mysqli_query($conn, "SELECT * FROM view_pelanggan");
                        while ($p = mysqli_fetch_assoc($q_pel)): ?>
                            <option value="<?= $p['id_pelanggan'] ?>"><?= $p['nama_pelanggan'] ?> (<?= $p['kota'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                    <select name="id_produk" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                        <?php 
                        mysqli_data_seek($q_produk_opt, 0);
                        while ($pr = mysqli_fetch_assoc($q_produk_opt)): ?>
                            <option value="<?= $pr['id_produk'] ?>"><?= $pr['nama_produk'] ?> (Stok: <?= $pr['stok'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="jumlah" value="1" min="1" placeholder="Jumlah" required class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                    <input type="number" name="diskon" value="0" min="0" max="100" placeholder="Diskon (%)" class="border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" name="tambah_transaksi" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black py-3 px-4 rounded-2xl transition shadow-lg shadow-emerald-600/25 active:scale-95">
                    Proses Transaksi & Potong Stok Otomatis
                </button>
            </form>
        </div>

        <!-- Tabel View Total Belanja & Function (Hanya Muncul untuk Administrator & Kasir POS) -->
        <div class="bg-white p-5 sm:p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
                        Rekapitulasi Belanja Member TokoKita
                    </h2>
                    <p class="text-[11px] text-slate-400">Integrasi <code>view_total_belanja</code> + Function <code>KategoriBelanja</code> & <code>HitungPajak</code></p>
                </div>
                <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full border border-indigo-200/60">
                    Akses Khusus Admin & Kasir
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-200">
                            <th class="p-3.5">ID</th>
                            <th class="p-3.5">Nama Pelanggan</th>
                            <th class="p-3.5">Frekuensi</th>
                            <th class="p-3.5">Total Belanja</th>
                            <th class="p-3.5">PPN 11% (Function)</th>
                            <th class="p-3.5 text-center">Status Member (Function)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($row = mysqli_fetch_assoc($q_rekap)): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-mono font-bold text-indigo-600">#<?= $row['id_pelanggan'] ?></td>
                            <td class="p-3.5 font-bold text-slate-800"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                            <td class="p-3.5 font-semibold text-slate-500"><?= $row['total_transaksi'] ?>x Transaksi</td>
                            <td class="p-3.5 font-black text-emerald-600 text-xs"><?= rupiah($row['total_belanja']) ?></td>
                            <td class="p-3.5 text-slate-400 font-medium"><?= rupiah($row['estimasi_ppn']) ?></td>
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 text-[10px] rounded-full font-black border
                                    <?= $row['kategori'] == 'Pelanggan Gold' ? 'bg-amber-100 text-amber-800 border-amber-300' : ($row['kategori'] == 'Pelanggan Silver' ? 'bg-slate-200 text-slate-700 border-slate-300' : 'bg-indigo-50 text-indigo-700 border-indigo-200') ?>">
                                    <?= $row['kategori'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        function toggleFormAlamatDashboard() {
            const form = document.getElementById('formAlamatDashboard');
            const box = document.getElementById('boxAlamatRingkas');
            if (form) {
                form.classList.toggle('hidden');
            }
            if (box && form) {
                if (!form.classList.contains('hidden')) {
                    box.classList.add('hidden');
                } else {
                    box.classList.remove('hidden');
                }
            }
        }
    </script>
</body>
</html>
