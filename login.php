<?php
session_start();
require_once 'koneksi.php';

// Jika sudah login, langsung arahkan ke dashboard index.php
if (isset($_SESSION['login_toko'])) {
    header("Location: index.php");
    exit;
}

// Otomatis membuat tabel user & akun default jika belum ada di db_tokokita
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    nama_lengkap VARCHAR(100),
    role VARCHAR(30)
)");

$cek_user = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_users");
$row_user = mysqli_fetch_assoc($cek_user);
if ($row_user['total'] == 0) {
    $pass_default = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) 
                         VALUES ('admin', '$pass_default', 'Administrator Toko', 'Super Admin')");
}

$error = "";
if (isset($_POST['btn_login'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $q = mysqli_query($conn, "SELECT * FROM tbl_users WHERE username = '$username'");
    if (mysqli_num_rows($q) === 1) {
        $data = mysqli_fetch_assoc($q);
        if (password_verify($password, $data['password'])) {
            $_SESSION['login_toko']   = true;
            $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
            $_SESSION['role']         = $data['role'];
            header("Location: index.php");
            exit;
        }
    }
    $error = "Username atau password yang Anda masukkan salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Penjualan TokoKita</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4 font-sans">

    <div class="max-w-4xl w-full bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
        
        <!-- Panel Kiri: Branding Visual -->
        <div class="bg-gradient-to-br from-indigo-600 to-violet-700 p-10 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 bg-white/20 px-3 py-1.5 rounded-full text-xs font-semibold tracking-wide backdrop-blur-sm">
                    <i data-lucide="database" class="w-4 h-4"></i> SQL Lanjutan • MariaDB
                </div>
                <h1 class="text-3xl font-extrabold mt-6 leading-tight">TokoKita POS & Inventory</h1>
                <p class="text-indigo-100 text-sm mt-3 leading-relaxed">
                    Sistem Informasi Penjualan Terintegrasi dengan Database View, Stored Procedure, Function, dan Trigger Otomatis.
                </p>
            </div>

            <div class="space-y-3 relative z-10 mt-8">
                <div class="flex items-center gap-3 text-xs bg-black/20 p-3 rounded-xl">
                    <i data-lucide="shield-check" class="w-5 h-5 text-emerald-300 shrink-0"></i>
                    <span>Otomatisasi pemotongan stok & audit log transaksi real-time</span>
                </div>
                <div class="text-xs text-indigo-200 flex justify-between items-center pt-2 border-t border-white/20">
                    <span>Akun Demo Default:</span>
                    <span class="font-mono bg-white/20 px-2 py-0.5 rounded text-white">admin / admin123</span>
                </div>
            </div>
        </div>

        <!-- Panel Kanan: Form Login -->
        <div class="bg-white p-10 flex flex-col justify-center">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-800">Selamat Datang Kembali</h2>
                <p class="text-sm text-slate-500 mt-1">Silakan masuk untuk mengelola data penjualan toko</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-xs font-medium flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                        <input type="text" name="username" value="admin" required
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                        <input type="password" id="inputPass" name="password" value="admin123" required
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <button type="button" onclick="togglePass()" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="btn_login"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2 mt-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Masuk ke Dashboard
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Belum punya akun?</span>
                <a href="register.php" class="text-indigo-600 font-bold hover:underline flex items-center gap-1">
                    Daftar Akun Baru →
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function togglePass() {
            const p = document.getElementById('inputPass');
            p.type = p.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>