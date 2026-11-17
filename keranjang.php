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

        // Mulai Transaksi Database (TCL COMMIT & ROLLBACK)
        mysqli_begin_transaction($conn);

        $ok_header = mysqli_query($conn, "INSERT INTO tbl_transaksi (tanggal, id_pelanggan, total_belanja) VALUES (CURDATE(), $id_pelanggan, $total_bersih)");
        $id_trx_baru = mysqli_insert_id($conn);

        $ok_detail = true;
        foreach ($_SESSION['keranjang'] as $item) {
            $id_p = $item['id_produk'];
            $nm_p = mysqli_real_escape_string($conn, $item['nama_produk']);
            $jml  = $item['jumlah'];
            $hrg  = $item['harga'];

            // Cek stok
            $cek_stok = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok FROM tbl_produk WHERE id_produk = $id_p"));
            if ($cek_stok['stok'] < $jml) {
                $ok_detail = false;
                break;
            }

            // Insert ke tbl_detail_transaksi
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100/70 text-slate-800 font-sans min-h-screen selection:bg-indigo-500 selection:text-white">

    <!-- Global TokoKita Navbar -->
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        
        <!-- Notifikasi -->
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3.5 rounded-2xl flex items-center justify-between gap-3 text-xs font-bold shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </div>
                    <span><?= $notif_sukses ?></span>
                </div>
                <a href="kategori.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0">
                    + Belanja Lagi
                </a>
            </div>
        <?php endif; ?>

        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold shadow-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i> 
                <span><?= $notif_error ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Katalog Pilih Produk (Kiri - 7 Kolom) -->
            <div class="lg:col-span-7 bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                            <i data-lucide="shopping-bag" class="w-4 h-4 text-indigo-600"></i> Pilih Produk Katalog
                        </h2>
                        <p class="text-[11px] text-slate-400">Pilih item untuk langsung dimasukkan ke keranjang</p>
                    </div>
                    <a href="kategori.php" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                        Semua Kategori <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Live Filter Input -->
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariItemKeranjang" onkeyup="filterItemKeranjang()" placeholder="Cari nama barang atau barcode..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 max-h-[620px] overflow-y-auto pr-1">
                    <?php while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                    <form method="POST" class="kartu-item border border-slate-200/70 rounded-2xl p-4 hover:border-indigo-500 hover:shadow-md transition flex flex-col justify-between bg-white group">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-md">
                                    <?= htmlspecialchars($pr['nama_kategori'] ?? 'Umum') ?>
                                </span>
                                <span class="text-[10.5px] font-bold text-slate-400">
                                    Stok: <?= $pr['stok'] ?>
                                </span>
                            </div>
                            <h3 class="font-extrabold text-xs text-slate-800 group-hover:text-indigo-600 transition line-clamp-1">
                                <?= htmlspecialchars($pr['nama_produk']) ?>
                            </h3>
                            <div class="font-mono text-[10px] text-slate-400 mt-0.5 flex items-center gap-1">
                                <i data-lucide="barcode" class="w-3 h-3 text-slate-400"></i>
                                <span><?= htmlspecialchars($pr['barcode'] ?? ('899' . str_pad($pr['id_produk'], 8, '0', STR_PAD_LEFT))) ?></span>
                            </div>
                            <p class="text-emerald-600 font-black text-sm mt-2"><?= rupiah($pr['harga']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 mt-3 pt-2.5 border-t border-slate-100">
                            <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                            <input type="number" name="jumlah" value="1" min="1" max="<?= $pr['stok'] ?>" 
                                class="w-14 border border-slate-300 rounded-xl p-2 text-xs text-center font-black focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" name="masuk_keranjang" 
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black py-2 px-3 rounded-xl flex items-center justify-center gap-1.5 transition active:scale-95 shadow-md shadow-indigo-600/20">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Beli
                            </button>
                        </div>
                    </form>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Panel Rincian Keranjang & Checkout (Kanan - 5 Kolom) -->
            <div class="lg:col-span-5 bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 space-y-4 sticky top-24">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h2 class="font-black text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4 text-emerald-600"></i>
                        <span><?= isPelanggan() ? 'Keranjang Belanja Anda' : 'Keranjang Kasir Multi-Item' ?></span>
                    </h2>
                    <?php if (!empty($_SESSION['keranjang'])): ?>
                        <a href="?kosongkan=1" onclick="return confirm('Kosongkan semua isi keranjang?')" 
                           class="text-[11px] text-rose-500 hover:text-rose-700 font-extrabold bg-rose-50 px-2 py-1 rounded-lg hover:bg-rose-100 transition">
                            Kosongkan
                        </a>
                    <?php endif; ?>
                </div>

                <?php 
                $grand_total = 0;
                if (empty($_SESSION['keranjang'])): ?>
                    <div class="text-center py-12 text-slate-400 space-y-2">
                        <div class="w-14 h-14 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto text-slate-300 shadow-inner">
                            <i data-lucide="shopping-cart" class="w-7 h-7"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-600">Keranjang belanja Anda masih kosong</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto">Silakan pilih produk dari katalog di sebelah kiri untuk mulai berbelanja.</p>
                        <a href="kategori.php" class="inline-flex items-center gap-1.5 mt-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs px-4 py-2 rounded-xl transition shadow-md shadow-indigo-600/25">
                            <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Buka Katalog Produk →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        <?php foreach ($_SESSION['keranjang'] as $item): 
                            $grand_total += $item['subtotal'];
                        ?>
                        <div class="flex justify-between items-center bg-slate-50 hover:bg-slate-100/70 p-3 rounded-2xl border border-slate-200/80 text-xs transition">
                            <div class="pr-2 truncate">
                                <p class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars($item['nama_produk']) ?></p>
                                <p class="text-[11px] text-slate-500 font-medium"><?= $item['jumlah'] ?> pcs × <?= rupiah($item['harga']) ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-black text-indigo-700 text-xs"><?= rupiah($item['subtotal']) ?></p>
                                <a href="?hapus_item=<?= $item['id_produk'] ?>" class="text-[10.5px] text-rose-500 hover:text-rose-700 font-bold hover:underline inline-flex items-center gap-0.5 mt-0.5">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['keranjang'])): ?>
                <form method="POST" class="pt-3 border-t border-slate-100 space-y-3.5 text-xs">
                    <!-- Grand Total Banner -->
                    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-4 rounded-2xl flex justify-between items-center shadow-lg border border-indigo-900/50">
                        <div>
                            <span class="text-[11px] text-slate-400 block font-medium">Subtotal Belanja:</span>
                            <span class="text-[10px] text-indigo-300 font-mono">Function `HitungTotal`</span>
                        </div>
                        <span class="text-lg font-black text-emerald-400"><?= rupiah($grand_total) ?></span>
                    </div>

                    <?php if (isPelanggan()): ?>
                        <!-- Identitas Pelanggan Otomatis Terkunci untuk Akun Pelanggan -->
                        <input type="hidden" name="id_pelanggan" value="<?= $id_pel_login ?>">
                        <div class="bg-indigo-50 border border-indigo-200 p-3 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-indigo-500 font-bold uppercase block">Akun Pemesan:</span>
                                <span class="text-xs font-black text-indigo-950"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                            </div>
                            <span class="bg-indigo-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-md">Verified</span>
                        </div>
                    <?php else: ?>
                        <!-- Pilihan Pelanggan Kasir POS -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Pelanggan Kasir</label>
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
                        <label class="block text-xs font-bold text-slate-600 mb-1">Potongan Diskon (%)</label>
                        <input type="number" name="diskon_persen" value="0" min="0" max="100" 
                            class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <button type="submit" name="checkout_keranjang" 
                        class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 active:scale-[0.98]">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i> Checkout Sekarang (TCL COMMIT)
                    </button>
                </form>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        function filterItemKeranjang() {
            const query = document.getElementById('cariItemKeranjang').value.toLowerCase();
            const cards = document.querySelectorAll('.kartu-item');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
