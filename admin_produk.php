<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif = "";
$is_error = false;

// Hapus Produk - Khusus Hak Akses Administrator (Kasir Dilarang Menghapus)
if (isset($_GET['hapus'])) {
    if (!isAdmin()) {
        $notif = "Akses Ditolak: Kasir tidak diizinkan menghapus barang inventori! Hak menghapus produk hanya dimiliki oleh Administrator.";
        $is_error = true;
    } else {
        $id_hapus = (int)$_GET['hapus'];
        // Cek apakah produk sudah tercatat pada detail transaksi
        $cek_trx = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_detail_transaksi WHERE id_produk = $id_hapus");
        $r_trx = mysqli_fetch_assoc($cek_trx);
        if ($r_trx && $r_trx['c'] > 0) {
            $notif = "Produk #$id_hapus tidak dapat dihapus karena sudah memiliki riwayat transaksi penjualan!";
            $is_error = true;
        } else {
            mysqli_query($conn, "DELETE FROM tbl_favorit WHERE id_produk = $id_hapus");
            if (mysqli_query($conn, "DELETE FROM tbl_produk WHERE id_produk = $id_hapus")) {
                $notif = "Produk #$id_hapus berhasil dihapus permanen oleh Administrator!";
                mysqli_query($conn, "INSERT INTO tbl_log (tabel_terkait, aksi, keterangan) VALUES ('tbl_produk', 'HAPUS PRODUK', 'Admin menghapus produk ID #$id_hapus')");
            } else {
                $notif = "Gagal menghapus produk: " . mysqli_error($conn);
                $is_error = true;
            }
        }
    }
}

if (isset($_POST['tambah'])) {
    $id_kat  = (int)$_POST['id_kategori'];
    $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
    $harga   = (float)$_POST['harga'];
    $stok    = (int)$_POST['stok'];
    $barcode = isset($_POST['barcode']) ? mysqli_real_escape_string($conn, trim($_POST['barcode'])) : '';
    $foto_url = getFotoProduk(trim($_POST['nama_produk']));
    $foto_file_path = '';
    if (empty($barcode)) {
        // Otomatis generate kode barcode 899xxxx
        $barcode = '899' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    }

    if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto_produk'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $notif = "Foto produk gagal diunggah. Periksa ukuran file dan coba lagi.";
            $is_error = true;
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $notif = "Ukuran foto produk maksimal 5 MB.";
            $is_error = true;
        } else {
            $image_info = @getimagesize($file['tmp_name']);
            $image_extensions = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png'
            ];
            if (defined('IMAGETYPE_WEBP')) {
                $image_extensions[IMAGETYPE_WEBP] = 'webp';
            }

            if (!$image_info || !isset($image_extensions[$image_info[2]])) {
                $notif = "Format foto harus berupa JPG, PNG, atau WebP.";
                $is_error = true;
            } else {
                $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'produk';
                if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    $notif = "Folder penyimpanan foto produk tidak dapat dibuat.";
                    $is_error = true;
                } else {
                    $file_name = 'sku-' . bin2hex(random_bytes(16)) . '.' . $image_extensions[$image_info[2]];
                    $foto_file_path = $upload_dir . DIRECTORY_SEPARATOR . $file_name;
                    if (move_uploaded_file($file['tmp_name'], $foto_file_path)) {
                        $foto_url = 'uploads/produk/' . $file_name;
                    } else {
                        $foto_file_path = '';
                        $notif = "Foto produk gagal disimpan ke folder upload.";
                        $is_error = true;
                    }
                }
            }
        }
    }

    if (!$is_error) {
        $foto = mysqli_real_escape_string($conn, $foto_url);
        if (mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok, barcode, foto_url) VALUES ($id_kat, '$nama', $harga, $stok, '$barcode', '$foto')")) {
            $notif = "Produk '$nama' (Barcode: $barcode) berhasil ditambahkan ke katalog inventori!";
        } else {
            if ($foto_file_path !== '' && is_file($foto_file_path)) {
                unlink($foto_file_path);
            }
            $notif = "Gagal menambahkan produk: " . mysqli_error($conn);
            $is_error = true;
        }
    } elseif ($foto_file_path !== '' && is_file($foto_file_path)) {
        unlink($foto_file_path);
    }
}

if (isset($_POST['update'])) {
    $id_p  = (int)$_POST['id_produk'];
    $harga = (float)$_POST['harga'];
    $stok  = (int)$_POST['stok'];
    mysqli_query($conn, "UPDATE tbl_produk SET harga=$harga, stok=$stok WHERE id_produk=$id_p");
    $notif = "Data harga & stok produk #$id_p berhasil diperbarui!";
}

if (isset($_GET['fav'])) {
    $id_f = (int)$_GET['fav'];
    mysqli_query($conn, "INSERT IGNORE INTO tbl_favorit (id_produk) VALUES ($id_f)");
    $notif = "Produk #$id_f berhasil disematkan ke daftar Favorit!";
}

$q_produk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori, HitungPajak(p.harga) AS pajak
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
");
$q_kat = mysqli_query($conn, "SELECT * FROM tbl_kategori ORDER BY nama_kategori ASC");

$stat_p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total_sku, COALESCE(SUM(stok), 0) AS total_unit FROM tbl_produk"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Library Barcode Scanner -->
    <script src="js/html5-qrcode.min.js"></script>
    <script>
        if (typeof Html5Qrcode === 'undefined') {
            document.write('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"><\/script>');
        }
    </script>
    <style>
        @keyframes scanLaser {
            0% { top: 4%; opacity: 0.8; }
            50% { top: 92%; opacity: 1; }
            100% { top: 4%; opacity: 0.8; }
        }
        .scanning-laser {
            position: absolute;
            left: 4%;
            width: 92%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #ef4444, #f43f5e, #ef4444, transparent);
            box-shadow: 0 0 14px 4px rgba(239, 68, 68, 0.85);
            animation: scanLaser 2s infinite ease-in-out;
            z-index: 20;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Notifikasi -->
        <?php if ($notif): ?>
            <div class="<?= $is_error ? 'bg-rose-50 border border-rose-300 text-rose-800' : 'bg-emerald-50 border border-emerald-300 text-emerald-800' ?> px-4 py-3 rounded-2xl text-xs font-semibold flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $is_error ? 'alert-triangle' : 'check-circle' ?>" class="w-4 h-4 <?= $is_error ? 'text-rose-600' : 'text-emerald-600' ?>"></i>
                    <span><?= $notif ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="<?= $is_error ? 'text-rose-600 hover:text-rose-800' : 'text-emerald-600 hover:text-emerald-800' ?>"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <!-- Form Tambah Produk Baru -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                        <i data-lucide="package-plus" class="w-4 h-4 text-indigo-600"></i> Tambah Master Produk Baru
                    </h2>
                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border <?= isAdmin() ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                        <?= isAdmin() ? 'Akses: Administrator (Bisa Tambah & Hapus)' : 'Akses: Kasir POS (Bisa Tambah, Dilarang Hapus)' ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span>Total SKU: <strong class="text-slate-700"><?= $stat_p['total_sku'] ?></strong></span>
                    <span>Total Stok: <strong class="text-emerald-600"><?= number_format($stat_p['total_unit']) ?> Unit</strong></span>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Kategori</label>
                    <select name="id_kategori" required class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php while ($k = mysqli_fetch_assoc($q_kat)): ?>
                            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Barcode / SKU</label>
                    <div class="relative flex">
                        <input type="text" id="inpBarcodeTambah" name="barcode" placeholder="Scan barcode..." 
                            class="w-full border border-slate-200 bg-slate-50 pl-3 pr-9 py-2.5 rounded-xl text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="button" onclick="bukaScanAdmin()" title="Buka Kamera Scanner Barcode"
                            class="absolute right-1 top-1 bottom-1 bg-indigo-600 hover:bg-indigo-700 text-white px-2 rounded-lg flex items-center justify-center text-xs transition">
                            <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Nama Produk</label>
                    <input type="text" id="inpNamaTambah" name="nama_produk" placeholder="Nama barang" required
                        class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Harga (Rp)</label>
                    <input type="number" name="harga" placeholder="Contoh: 25000" required
                        class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Stok Awal</label>
                    <input type="number" name="stok" placeholder="Contoh: 50" required
                        class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Foto Produk</label>
                    <input type="file" id="fotoProduk" name="foto_produk" accept=".jpg,.jpeg,.png,.webp" onchange="previewFotoProduk(this.files)"
                        class="w-full border border-slate-200 bg-slate-50 p-2 rounded-xl text-[10px]">
                    <p class="mt-1 text-[9px] text-slate-400">Opsional, maks. 5 MB. Kosongkan untuk gambar otomatis.</p>
                    <img id="previewFotoProduk" src="<?= htmlspecialchars(getFotoProduk(''), ENT_QUOTES, 'UTF-8') ?>" alt="Pratinjau foto produk"
                        class="mt-1 h-12 w-12 rounded-lg border border-slate-200 object-cover" loading="lazy">
                </div>
                <div class="flex items-end">
                    <button type="submit" name="tambah"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold p-2.5 rounded-xl text-xs shadow-md shadow-indigo-600/30 transition flex items-center justify-center gap-1.5">
                        <i data-lucide="plus" class="w-4 h-4"></i> Simpan SKU
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Inventori Produk -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="relative flex-1 max-w-md">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="cariProduk" onkeyup="filterTable('cariProduk', 'tabelAdminProduk')" placeholder="Cari nama produk, kategori, ID..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                <div class="flex items-center gap-2">
                    <a href="kasir_pos.php" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow flex items-center gap-1.5 transition">
                        <i data-lucide="calculator" class="w-4 h-4"></i> Buka Terminal POS
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="tabelAdminProduk" class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 uppercase tracking-wider text-[10px] border-b">
                            <th class="p-3.5">ID</th>
                            <th class="p-3.5">Foto Produk</th>
                            <th class="p-3.5">Barcode / SKU</th>
                            <th class="p-3.5">Nama Produk</th>
                            <th class="p-3.5">Kategori</th>
                            <th class="p-3.5">Update Cepat Harga & Stok</th>
                            <th class="p-3.5">PPN 11%</th>
                            <th class="p-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while ($p = mysqli_fetch_assoc($q_produk)): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5 font-mono font-bold text-indigo-600">#<?= $p['id_produk'] ?></td>
                            <td class="p-3.5">
                                <img src="<?= htmlspecialchars(getFotoProduk($p['nama_produk'], $p['foto_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="h-12 w-12 rounded-lg border border-slate-200 object-cover" loading="lazy"
                                    onerror="this.onerror=null;this.src='<?= htmlspecialchars(getFotoProduk(''), ENT_QUOTES, 'UTF-8') ?>'">
                            </td>
                            <td class="p-3.5 font-mono text-[11px] font-bold text-slate-700">
                                <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[10px]">
                                    <?= htmlspecialchars($p['barcode'] ?? ('899' . str_pad($p['id_produk'], 8, '0', STR_PAD_LEFT))) ?>
                                </span>
                            </td>
                            <td class="p-3.5 font-bold text-slate-800"><?= htmlspecialchars($p['nama_produk']) ?></td>
                            <td class="p-3.5">
                                <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                    <?= htmlspecialchars($p['nama_kategori'] ?? 'Umum') ?>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <form method="POST" class="flex items-center gap-1.5">
                                    <input type="hidden" name="id_produk" value="<?= $p['id_produk'] ?>">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1.5 text-[10px] font-bold text-slate-400">Rp</span>
                                        <input type="number" name="harga" value="<?= (int)$p['harga'] ?>" class="border border-slate-200 rounded-lg pl-6 pr-2 py-1 text-xs font-bold w-28 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    </div>
                                    <input type="number" name="stok" value="<?= $p['stok'] ?>" class="border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold w-16 text-center focus:outline-none focus:ring-1 focus:ring-indigo-500" title="Stok">
                                    <button type="submit" name="update" class="bg-slate-900 hover:bg-slate-800 text-white text-[11px] px-2.5 py-1 rounded-lg font-bold transition">Simpan</button>
                                </form>
                            </td>
                            <td class="p-3.5 text-slate-400 font-semibold"><?= rupiah($p['pajak']) ?></td>
                            <td class="p-3.5 text-center space-x-1 whitespace-nowrap">
                                <a href="detail_produk.php?id=<?= $p['id_produk'] ?>" class="text-indigo-600 hover:bg-indigo-50 px-2 py-1 rounded-lg font-bold transition text-xs">Detail</a>
                                <a href="?fav=<?= $p['id_produk'] ?>" class="text-rose-500 hover:bg-rose-50 px-2 py-1 rounded-lg font-bold transition text-xs" title="Tambah Favorit">♥ Favorit</a>
                                <?php if (isAdmin()): ?>
                                    <a href="?hapus=<?= $p['id_produk'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus produk <?= htmlspecialchars($p['nama_produk']) ?> secara permanen?')" 
                                       class="text-rose-600 hover:text-white hover:bg-rose-600 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded-lg font-bold transition text-xs inline-flex items-center gap-1">
                                        <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 bg-slate-100 border border-slate-200 px-2 py-1 rounded-lg font-bold text-[10px] inline-flex items-center gap-1 cursor-not-allowed select-none" title="Kasir dilarang menghapus barang inventori">
                                        <i data-lucide="lock" class="w-2.5 h-2.5 text-slate-400"></i> Hapus (Khusus Admin)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>

    <!-- MODAL SCANNER BARCODE UNTUK INPUT PRODUK BARU -->
    <div id="modalScanAdmin" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600/30 flex items-center justify-center text-indigo-400">
                        <i data-lucide="scan-barcode" class="w-4 h-4 animate-pulse"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-white">Scan Barcode Produk Baru</h3>
                        <p class="text-[11px] text-slate-400">Arahkan kamera ke barcode kemasan barang</p>
                    </div>
                </div>
                <button type="button" onclick="tutupScanAdmin()" class="text-slate-400 hover:text-white">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4 text-xs">
                <div class="relative bg-slate-950 rounded-2xl overflow-hidden aspect-video min-h-[220px] max-h-[260px] flex items-center justify-center">
                    <div id="readerAdmin" class="w-full h-full flex items-center justify-center"></div>
                    <div class="scanning-laser"></div>
                    <div id="phAdmin" class="absolute inset-0 flex flex-col items-center justify-center text-center p-4 bg-slate-900/90 z-10">
                        <i data-lucide="camera" class="w-10 h-10 text-indigo-400 mb-2"></i>
                        <p class="font-bold text-white mb-1">Kamera Siap</p>
                        <button type="button" onclick="startScanAdmin()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-xl mt-2 flex items-center gap-1.5 shadow">
                            <i data-lucide="play" class="w-3.5 h-3.5"></i> Nyalakan Kamera
                        </button>
                    </div>
                </div>

                <!-- Bantuan jika izin kamera belum aktif -->
                <div id="errScanAdmin" class="hidden bg-amber-50 border border-amber-300 text-amber-900 p-3 rounded-2xl text-[11px]">
                    <p class="font-bold">⚠️ Kamera Tidak Aktif</p>
                    <p class="text-slate-600 mt-1">Pastikan browser mengizinkan kamera (klik tanda gembok 🔒 di URL), atau Anda dapat langsung mengetik nomor barcode secara manual atau menembakkan Barcode Gun.</p>
                </div>

                <div class="space-y-2">
                    <label class="block font-bold text-slate-700">Atau Pilih Foto / Gambar Barcode:</label>
                    <input type="file" id="fileBarcodeAdmin" accept="image/*" onchange="scanAdminDariFile(this.files)" class="w-full border border-slate-200 bg-slate-50 p-2 rounded-xl text-xs">
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        lucide.createIcons();

        const fotoProdukDefault = <?= json_encode(getFotoProduk('')) ?>;

        function previewFotoProduk(files) {
            const preview = document.getElementById('previewFotoProduk');
            if (!preview) return;
            if (!files || files.length === 0) {
                preview.src = fotoProdukDefault;
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                if (typeof reader.result === 'string') preview.src = reader.result;
            };
            reader.onerror = () => {
                preview.src = fotoProdukDefault;
                tampilkanToast('Foto tidak dapat ditampilkan. Silakan pilih file gambar lain.', 'error');
            };
            reader.readAsDataURL(files[0]);
        }

        let qrAdmin = null;
        let isCamAdminRunning = false;

        function bukaScanAdmin() {
            const m = document.getElementById('modalScanAdmin');
            m.classList.remove('hidden');
            m.classList.add('flex');
            startScanAdmin();
        }

        function tutupScanAdmin() {
            stopScanAdmin();
            const m = document.getElementById('modalScanAdmin');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }

        function startScanAdmin() {
            if (typeof Html5Qrcode === 'undefined') return;
            const ph = document.getElementById('phAdmin');
            const errBox = document.getElementById('errScanAdmin');
            if (errBox) errBox.classList.add('hidden');

            if (!qrAdmin) qrAdmin = new Html5Qrcode("readerAdmin");

            Html5Qrcode.getCameras().then(devices => {
                let targetId = (devices && devices.length > 0) ? devices[0].id : { video: true };
                qrAdmin.start(
                    targetId,
                    { fps: 15, qrbox: { width: 220, height: 160 } },
                    (code) => {
                        selesaiScanAdmin(code);
                    },
                    () => {}
                ).then(() => {
                    isCamAdminRunning = true;
                    if (ph) ph.classList.add('hidden');
                }).catch(() => {
                    qrAdmin.start({ video: true }, { fps: 15, qrbox: { width: 220, height: 160 } }, (code) => {
                        selesaiScanAdmin(code);
                    }, () => {}).then(() => {
                        isCamAdminRunning = true;
                        if (ph) ph.classList.add('hidden');
                    }).catch(err => {
                        if (ph) ph.classList.remove('hidden');
                        if (errBox) errBox.classList.remove('hidden');
                    });
                });
            }).catch(err => {
                qrAdmin.start({ video: true }, { fps: 15, qrbox: { width: 220, height: 160 } }, (code) => {
                    selesaiScanAdmin(code);
                }, () => {}).then(() => {
                    isCamAdminRunning = true;
                    if (ph) ph.classList.add('hidden');
                }).catch(err2 => {
                    if (ph) ph.classList.remove('hidden');
                    if (errBox) errBox.classList.remove('hidden');
                });
            });
        }

        function stopScanAdmin() {
            if (qrAdmin && isCamAdminRunning) {
                qrAdmin.stop().then(() => {
                    isCamAdminRunning = false;
                    const ph = document.getElementById('phAdmin');
                    if (ph) ph.classList.remove('hidden');
                }).catch(e => console.error(e));
            }
        }

        function scanAdminDariFile(files) {
            if (!files || files.length === 0) return;
            if (!qrAdmin) qrAdmin = new Html5Qrcode("readerAdmin");
            qrAdmin.scanFile(files[0], true).then(code => {
                selesaiScanAdmin(code);
            }).catch(err => {
                alert("Barcode tidak terdeteksi pada gambar. Pastikan gambar jelas.");
            });
        }

        function selesaiScanAdmin(code) {
            playScannerBeep();
            const inp = document.getElementById('inpBarcodeTambah');
            if (inp) {
                inp.value = code;
                const inpNama = document.getElementById('inpNamaTambah');
                if (inpNama) inpNama.focus();
            }
            tutupScanAdmin();
            tampilkanToast("✅ Barcode terdeteksi: " + code, "success");
        }
    </script>
</body>
</html>
