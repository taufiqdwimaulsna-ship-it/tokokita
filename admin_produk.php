<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif = "";

if (isset($_POST['tambah'])) {
    $id_kat = (int)$_POST['id_kategori'];
    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
    $harga  = (float)$_POST['harga'];
    $stok   = (int)$_POST['stok'];
    mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES ($id_kat, '$nama', $harga, $stok)");
    $notif = "Produk '$nama' berhasil ditambahkan ke katalog inventori!";
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
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Notifikasi -->
        <?php if ($notif): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-semibold flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span><?= $notif ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <!-- Form Tambah Produk Baru -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                    <i data-lucide="package-plus" class="w-4 h-4 text-indigo-600"></i> Tambah Master Produk Baru
                </h2>
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span>Total SKU: <strong class="text-slate-700"><?= $stat_p['total_sku'] ?></strong></span>
                    <span>Total Stok: <strong class="text-emerald-600"><?= number_format($stat_p['total_unit']) ?> Unit</strong></span>
                </div>
            </div>

            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Kategori</label>
                    <select name="id_kategori" required class="w-full border border-slate-200 bg-slate-50 p-2.5 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php while ($k = mysqli_fetch_assoc($q_kat)): ?>
                            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Nama Produk</label>
                    <input type="text" name="nama_produk" placeholder="Nama barang" required
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
                            <td class="p-3.5 text-center space-x-1.5">
                                <a href="detail_produk.php?id=<?= $p['id_produk'] ?>" class="text-indigo-600 hover:bg-indigo-50 px-2 py-1 rounded-lg font-bold transition">Detail</a>
                                <a href="?fav=<?= $p['id_produk'] ?>" class="text-rose-500 hover:bg-rose-50 px-2 py-1 rounded-lg font-bold transition" title="Tambah Favorit">♥ Favorit</a>
                            </td>
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
