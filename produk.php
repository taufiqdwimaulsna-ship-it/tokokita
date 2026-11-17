<?php
session_start();
if (!isset($_SESSION['login_toko'])) { header("Location: login.php"); exit; }
require_once 'koneksi.php';

$notif_sukses = "";
$notif_error  = "";

// Tambah Produk
if (isset($_POST['simpan_produk'])) {
    $id_kat = (int)$_POST['id_kategori'];
    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
    $harga  = (float)$_POST['harga'];
    $stok   = (int)$_POST['stok'];

    if (mysqli_query($conn, "INSERT INTO tbl_produk (id_kategori, nama_produk, harga, stok) VALUES ($id_kat, '$nama', $harga, $stok)")) {
        $notif_sukses = "Produk '$nama' berhasil ditambahkan!";
    }
}

// Update Harga & Stok Produk
if (isset($_POST['edit_produk'])) {
    $id_p  = (int)$_POST['id_produk'];
    $nama  = mysqli_real_escape_string($conn, trim($_POST['nama_produk']));
    $harga = (float)$_POST['harga'];
    $stok  = (int)$_POST['stok'];

    mysqli_query($conn, "UPDATE tbl_produk SET nama_produk='$nama', harga=$harga, stok=$stok WHERE id_produk=$id_p");
    $notif_sukses = "Data produk #$id_p berhasil diperbarui!";
}

// Hapus Produk
if (isset($_GET['hapus'])) {
    $id_p = (int)$_GET['hapus'];
    if (mysqli_query($conn, "DELETE FROM tbl_produk WHERE id_produk=$id_p")) {
        $notif_sukses = "Produk berhasil dihapus!";
    } else {
        $notif_error = "Produk tidak bisa dihapus karena sudah tercatat pada detail transaksi.";
    }
}

$q_produk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori,
           HitungPajak(p.harga) AS ppn_11,
           HitungDiskon(p.harga, 10) AS diskon_10
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
");
$q_kat = mysqli_query($conn, "SELECT * FROM tbl_kategori");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Produk - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <?php if ($notif_sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif_sukses ?></div>
        <?php endif; ?>
        <?php if ($notif_error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl text-sm font-medium"><?= $notif_error ?></div>
        <?php endif; ?>

        <!-- Form Tambah Produk -->
        <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="font-bold text-base mb-4 flex items-center gap-2">
                <i data-lucide="package-plus" class="w-5 h-5 text-indigo-600"></i> Tambah Produk Baru ke Inventori (`tbl_produk`)
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                <select name="id_kategori" required class="border p-2.5 rounded-xl text-sm">
                    <option value="">-- Pilih Kategori --</option>
                    <?php while ($k = mysqli_fetch_assoc($q_kat)): ?>
                        <option value="<?= $k['id_kategori'] ?>"><?= $k['nama_kategori'] ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="nama_produk" placeholder="Nama Produk" required class="border p-2.5 rounded-xl text-sm">
                <input type="number" name="harga" placeholder="Harga Satuan (Rp)" required class="border p-2.5 rounded-xl text-sm">
                <input type="number" name="stok" placeholder="Stok Awal" required class="border p-2.5 rounded-xl text-sm">
                <button type="submit" name="simpan_produk" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm py-2.5 transition">
                    + Simpan Produk
                </button>
            </div>
        </form>

        <!-- Tabel Master Produk -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="font-bold text-base mb-4">Master Data Produk & Kalkulasi Stored Function (`HitungPajak` & `HitungDiskon`)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-left text-xs text-slate-600">
                            <th class="p-3">ID</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3">Edit Nama, Harga & Stok</th>
                            <th class="p-3">PPN 11% (`HitungPajak`)</th>
                            <th class="p-3">Diskon 10% (`HitungDiskon`)</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pr = mysqli_fetch_assoc($q_produk)): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 font-mono text-xs">#<?= $pr['id_produk'] ?></td>
                            <td class="p-3"><span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full text-xs font-semibold"><?= $pr['nama_kategori'] ?? 'Umum' ?></span></td>
                            <td class="p-3">
                                <form method="POST" class="flex flex-wrap items-center gap-1.5">
                                    <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                                    <input type="text" name="nama_produk" value="<?= htmlspecialchars($pr['nama_produk']) ?>" class="border rounded-lg px-2 py-1 text-xs w-40 font-semibold">
                                    <input type="number" name="harga" value="<?= (int)$pr['harga'] ?>" class="border rounded-lg px-2 py-1 text-xs w-28">
                                    <input type="number" name="stok" value="<?= $pr['stok'] ?>" class="border rounded-lg px-2 py-1 text-xs w-16 text-center">
                                    <button type="submit" name="edit_produk" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-2.5 py-1 rounded-lg font-semibold">Simpan</button>
                                </form>
                            </td>
                            <td class="p-3 text-slate-600">Rp <?= number_format($pr['ppn_11'], 0, ',', '.') ?></td>
                            <td class="p-3 text-amber-700 font-medium">Rp <?= number_format($pr['diskon_10'], 0, ',', '.') ?></td>
                            <td class="p-3 text-right">
                                <a href="?hapus=<?= $pr['id_produk'] ?>" onclick="return confirm('Hapus produk ini?')" class="text-rose-600 hover:underline text-xs font-semibold">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>