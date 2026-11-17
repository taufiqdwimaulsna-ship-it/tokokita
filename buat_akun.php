<?php
require_once 'functions.php';

$sukses = "";
$error  = "";

if (isset($_POST['daftar'])) {
    $nama     = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $role     = mysqli_real_escape_string($conn, $_POST['role']);
    $pass     = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $q = mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) VALUES ('$username', '$pass', '$nama', '$role')");
    if ($q) {
        $sukses = "Akun '$username' berhasil dibuat! Silakan login.";
    } else {
        $error = "Username '$username' sudah digunakan, pilih username lain.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Akun Baru - TokoKita</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4 font-sans">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 space-y-5">
        <div class="text-center">
            <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg">
                <i data-lucide="user-plus" class="w-6 h-6"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Buat Akun TokoKita</h1>
            <p class="text-xs text-slate-500 mt-1">Registrasi staf kasir, admin, atau pelanggan baru</p>
        </div>

        <?php if ($sukses): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3 rounded-xl text-xs font-semibold text-center">
                <?= $sukses ?> <a href="login.php" class="underline font-bold ml-1">Klik untuk Login →</a>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3 rounded-xl text-xs font-semibold text-center"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="text-xs font-bold text-slate-600">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" required placeholder="Masukkan nama lengkap" class="w-full border p-2.5 rounded-xl text-sm mt-1">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Username</label>
                <input type="text" name="username" required placeholder="Username login" class="w-full border p-2.5 rounded-xl text-sm mt-1">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Password</label>
                <input type="password" name="password" required placeholder="Minimal 6 karakter" class="w-full border p-2.5 rounded-xl text-sm mt-1">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-600">Peran (Role)</label>
                <select name="role" class="w-full border p-2.5 rounded-xl text-sm mt-1">
                    <option value="Kasir POS">Kasir POS</option>
                    <option value="Super Admin">Super Admin</option>
                    <option value="Pelanggan">Pelanggan</option>
                </select>
            </div>
            <button type="submit" name="daftar" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-lg transition">
                Daftar Akun Sekarang
            </button>
        </form>

        <p class="text-center text-xs text-slate-500 pt-2 border-t">
            Sudah punya akun? <a href="login.php" class="text-indigo-600 font-bold hover:underline">Masuk di sini</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>