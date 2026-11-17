<?php
require_once 'functions.php';
cekLogin();

if (isset($_GET['tambah'])) {
    $id_p = (int)$_GET['tambah'];
    mysqli_query($conn, "INSERT IGNORE INTO tbl_favorit (id_produk) VALUES ($id_p)");
    header("Location: favorit.php");
    exit;
}

if (isset($_GET['hapus'])) {
    $id_f = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM tbl_favorit WHERE id_produk = $id_f");
    header("Location: favorit.php");
    exit;
}

$q_fav = mysqli_query($conn, "
    SELECT f.waktu, p.*, k.nama_kategori
    FROM tbl_favorit f
    JOIN tbl_produk p ON f.id_produk = p.id_produk
    LEFT JOIN tbl_kategori k ON p.id_kategori = k.id_kategori
    ORDER BY f.id_favorit DESC
");
$q_all = mysqli_query($conn, "SELECT * FROM tbl_produk");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Produk Favorit - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen">
    <?php include 'navbar.php'; ?>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="text-lg font-bold flex items-center gap-2 text-rose-600">
                    <i data-lucide="heart" class="w-5 h-5"></i> Katalog Produk Favorit (`favorit.php`)
                </h1>
                <p class="text-xs text-slate-500">Daftar produk unggulan yang paling sering dicari pelanggan</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <select name="tambah" class="border p-2 rounded-xl text-xs">
                    <?php while ($a = mysqli_fetch_assoc($q_all)): ?>
                        <option value="<?= $a['id_produk'] ?>"><?= $a['nama_produk'] ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="bg-rose-600 text-white px-3.5 py-2 rounded-xl text-xs font-semibold">+ Tambah Favorit</button>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php while ($f = mysqli_fetch_assoc($q_fav)): ?>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] bg-rose-50 text-rose-600 px-2.5 py-0.5 rounded-full font-bold"><?= $f['nama_kategori'] ?? 'Umum' ?></span>
                        <a href="?hapus=<?= $f['id_produk'] ?>" class="text-xs text-slate-400 hover:text-rose-600">✕ Hapus</a>
                    </div>
                    <h3 class="font-bold text-base mt-3"><?= $f['nama_produk'] ?></h3>
                    <p class="text-xl font-extrabold text-indigo-700 mt-1"><?= rupiah($f['harga']) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Stok: <?= $f['stok'] ?> unit</p>
                </div>
                <a href="detail_produk.php?id=<?= $f['id_produk'] ?>" class="mt-4 bg-slate-900 hover:bg-indigo-600 text-white text-center py-2 rounded-xl text-xs font-semibold transition">
                    Lihat Detail Produk
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>