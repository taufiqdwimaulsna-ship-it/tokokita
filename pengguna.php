<?php
require_once 'functions.php';
batasiAksesPelanggan();

$notif_sukses = "";
$notif_error  = "";

if (isset($_POST['tambah_user'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $nama     = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $role     = mysqli_real_escape_string($conn, $_POST['role']);
    $pass     = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) VALUES ('$username', '$pass', '$nama', '$role')")) {
        $notif_sukses = "Akun pengguna '$username' berhasil ditambahkan!";
    } else {
        $notif_error = "Gagal: Username '$username' sudah terdaftar.";
    }
}

if (isset($_GET['hapus'])) {
    $id_u = (int)$_GET['hapus'];
    if ($id_u === 1) {
        $notif_error = "Akun Super Admin utama tidak boleh dihapus!";
    } else {
        mysqli_query($conn, "DELETE FROM tbl_users WHERE id_user = $id_u");
        $notif_sukses = "Akun pengguna berhasil dihapus!";
    }
}

$q_users = mysqli_query($conn, "SELECT * FROM tbl_users ORDER BY id_user ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pengguna - TokoKita</title>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form method="POST" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <h2 class="font-bold text-base flex items-center gap-2 border-b pb-3">
                    <i data-lucide="shield-plus" class="w-5 h-5 text-indigo-600"></i> Tambah Pengguna Sistem
                </h2>
                <input type="text" name="nama_lengkap" placeholder="Nama Lengkap Petugas" required class="w-full border p-2.5 rounded-xl text-sm">
                <input type="text" name="username" placeholder="Username Login" required class="w-full border p-2.5 rounded-xl text-sm">
                <input type="password" name="password" placeholder="Password" required class="w-full border p-2.5 rounded-xl text-sm">
                <select name="role" class="w-full border p-2.5 rounded-xl text-sm">
                    <option value="Super Admin">Super Admin</option>
                    <option value="Kasir POS">Kasir POS</option>
                    <option value="Gudang">Staf Gudang</option>
                </select>
                <button type="submit" name="tambah_user" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-xl transition">
                    Simpan Pengguna Baru
                </button>
            </form>

            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="font-bold text-base mb-4">Daftar Pengguna Aktif (`tbl_users`)</h2>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-left text-xs text-slate-600">
                            <th class="p-3">ID</th>
                            <th class="p-3">Nama Lengkap</th>
                            <th class="p-3">Username</th>
                            <th class="p-3">Hak Akses (Role)</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($q_users)): ?>
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3">#<?= $u['id_user'] ?></td>
                            <td class="p-3 font-semibold"><?= $u['nama_lengkap'] ?></td>
                            <td class="p-3 font-mono text-xs text-indigo-600"><?= $u['username'] ?></td>
                            <td class="p-3"><span class="bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-full text-xs font-bold"><?= $u['role'] ?></span></td>
                            <td class="p-3 text-right">
                                <?php if ($u['id_user'] != 1): ?>
                                    <a href="?hapus=<?= $u['id_user'] ?>" onclick="return confirm('Hapus pengguna ini?')" class="text-rose-600 hover:underline text-xs font-semibold">Hapus</a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Utama</span>
                                <?php endif; ?>
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