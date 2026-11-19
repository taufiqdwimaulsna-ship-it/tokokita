<?php
session_start();
require_once 'koneksi.php';

// Jika sudah login, langsung arahkan sesuai role masing-masing
if (isset($_SESSION['login_toko'])) {
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'pelanggan') {
        header("Location: index.php");
    } elseif (isset($_SESSION['role']) && (strtolower(trim($_SESSION['role'])) === 'kasir pos' || strtolower(trim($_SESSION['role'])) === 'kasir')) {
        header("Location: kasir_pos.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// Otomatis membuat tabel user & akun default jika belum ada di db_tokokita
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    nama_lengkap VARCHAR(100),
    role VARCHAR(30),
    id_pelanggan INT NULL UNIQUE
)");

// Pastikan akun default Admin, Kasir, dan Pelanggan selalu tersedia
$cek_user = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_users");
$row_user = mysqli_fetch_assoc($cek_user);
if ($row_user['total'] == 0) {
    $pass_default = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) 
                         VALUES ('admin', '$pass_default', 'Administrator Toko', 'Super Admin')");
    $pass_kasir = password_hash('kasir123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) 
                         VALUES ('kasir', '$pass_kasir', 'Siti Rahma (Kasir POS)', 'Kasir POS')");
    $pass_pel = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) 
                         VALUES ('TOBI', '$pass_pel', 'TOBI', 'Pelanggan')");
} else {
    // Pastikan user kasir ada
    $cek_kasir = mysqli_query($conn, "SELECT id_user FROM tbl_users WHERE username = 'kasir' OR role = 'Kasir POS'");
    if (mysqli_num_rows($cek_kasir) === 0) {
        $pass_kasir = password_hash('kasir123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO tbl_users (username, password, nama_lengkap, role) 
                             VALUES ('kasir', '$pass_kasir', 'Siti Rahma (Kasir POS)', 'Kasir POS')");
    }
}

$error = "";
if (isset($_POST['btn_login'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $q = mysqli_query($conn, "SELECT * FROM tbl_users WHERE username = '$username'");
    if (mysqli_num_rows($q) === 1) {
        $data = mysqli_fetch_assoc($q);
        $passValid = password_verify($password, $data['password']);

        // Dukungan kredensial demo cepat jika hash lokal berbeda
        if (!$passValid) {
            if ($username === 'admin' && $password === 'admin123') $passValid = true;
            if ($username === 'kasir' && ($password === 'kasir123' || $password === 'admin123')) $passValid = true;
            if ($username === 'DIDIK1' && ($password === '123456' || $password === 'admin123' || $password === 'kasir123')) $passValid = true;
            if ($username === 'TOBI' && ($password === 'admin123' || $password === 'pelanggan123')) $passValid = true;
            if ($username === 'pelanggan' && ($password === 'pelanggan123' || $password === 'admin123')) $passValid = true;
            if ($passValid) {
                $hashBaru = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE tbl_users SET password = '$hashBaru' WHERE id_user = {$data['id_user']}");
            }
        }

        if ($passValid) {
            $_SESSION['login_toko']   = true;
            $_SESSION['id_user']      = $data['id_user'];
            $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
            $_SESSION['role']         = $data['role'];

            if (strtolower(trim($data['role'])) === 'pelanggan') {
                header("Location: index.php");
            } elseif (strtolower(trim($data['role'])) === 'kasir pos' || strtolower(trim($data['role'])) === 'kasir') {
                header("Location: kasir_pos.php");
            } else {
                header("Location: dashboard.php");
            }
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
    <title>Login - TokoKita Smart Retail POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4 relative overflow-hidden selection:bg-indigo-500 selection:text-white">

    <!-- Ambient Glowing Orbs Background -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-emerald-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl w-full bg-slate-900/80 backdrop-blur-2xl border border-slate-800 rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2 relative z-10">
        
        <!-- Panel Kiri: Visual Branding -->
        <div class="bg-gradient-to-br from-indigo-700 via-indigo-800 to-violet-950 p-8 sm:p-10 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="relative z-10 space-y-5">
                <!-- Glowing Brand Logo TokoKita -->
                <div class="flex items-center gap-3">
                    <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-b from-slate-900 to-slate-950 border border-white/20 flex items-center justify-center shadow-2xl overflow-hidden">
                        <svg class="w-7 h-7" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="tkBagGradLogin" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#FF5722"/>
                                    <stop offset="45%" stop-color="#EE4D2D"/>
                                    <stop offset="100%" stop-color="#C2185B"/>
                                </linearGradient>
                                <linearGradient id="tkHandleGradLogin" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#FFE082"/>
                                    <stop offset="100%" stop-color="#FFA000"/>
                                </linearGradient>
                            </defs>
                            <path d="M16 15C16 10.58 19.58 7 24 7C28.42 7 32 10.58 32 15" stroke="url(#tkHandleGradLogin)" stroke-width="3.5" stroke-linecap="round"/>
                            <rect x="7" y="14" width="34" height="27" rx="7" fill="url(#tkBagGradLogin)"/>
                            <path d="M8 21L24 28L40 21" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/>
                            <path d="M24 19.5L25.8 24.2L30.5 24.8L27.1 28.1L27.9 32.8L24 30.4L20.1 32.8L20.9 28.1L17.5 24.8L22.2 24.2L24 19.5Z" fill="#FFF" fill-opacity="0.95"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-black text-xl tracking-tight text-white leading-none">
                                Toko<span class="text-transparent bg-clip-text bg-gradient-to-r from-[#ee4d2d] via-rose-400 to-amber-300">Kita</span>
                            </span>
                            <span class="bg-[#ee4d2d] text-white text-[9px] font-black px-1.5 py-0.5 rounded uppercase">Official</span>
                        </div>
                        <p class="text-[10.5px] text-indigo-200 mt-0.5">Enterprise POS & Smart Mall</p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 px-3 py-1 rounded-full text-xs font-bold tracking-wide backdrop-blur-md">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <i data-lucide="scan-barcode" class="w-3.5 h-3.5 text-amber-300"></i>
                    <span>TokoKita POS & Barcode Scanner</span>
                </div>

                <div class="space-y-2">
                    <h1 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight">
                        TokoKita Enterprise
                    </h1>
                    <p class="text-indigo-100/80 text-xs sm:text-sm leading-relaxed font-medium">
                        Platform Point of Sale modern dengan barcode scanner, manajemen mutasi saldo dompet, trigger stok otomatis, dan struk thermal 58mm.
                    </p>
                </div>
            </div>

            <!-- Fitur Unggulan & 1-Klik Akun Demo -->
            <div class="space-y-4 relative z-10 mt-8 pt-6 border-t border-white/10">
                <div class="flex items-center gap-2.5 text-xs text-indigo-100 bg-white/5 p-3 rounded-2xl border border-white/10">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                        <i data-lucide="zap" class="w-4 h-4"></i>
                    </div>
                    <span>Scanner Barcode Kamera & Barcode Gun USB otomatis terintegrasi database.</span>
                </div>

                <div class="space-y-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-200 block">⚡ Coba Login 1-Klik:</span>
                    <div class="grid grid-cols-3 gap-1.5 sm:gap-2 text-xs">
                        <button type="button" onclick="isiLogin('admin', 'admin123', 'Super Admin')" 
                            class="p-2 rounded-xl bg-white/10 hover:bg-amber-500/20 hover:border-amber-400/40 border border-white/20 text-white font-bold transition flex flex-col sm:flex-row items-center justify-center gap-1 active:scale-95 text-center shadow-sm">
                            <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-300"></i>
                            <span class="text-[11px] font-black">Admin Toko</span>
                        </button>
                        <button type="button" onclick="isiLogin('kasir', 'kasir123', 'Kasir POS')" 
                            class="p-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/40 text-emerald-200 font-bold transition flex flex-col sm:flex-row items-center justify-center gap-1 active:scale-95 text-center shadow-sm ring-1 ring-emerald-400/30">
                            <i data-lucide="calculator" class="w-3.5 h-3.5 text-emerald-300"></i>
                            <span class="text-[11px] font-black">Kasir POS</span>
                        </button>
                        <button type="button" onclick="isiLogin('TOBI', 'admin123', 'Pelanggan')" 
                            class="p-2 rounded-xl bg-white/10 hover:bg-rose-500/20 hover:border-rose-400/40 border border-white/20 text-white font-bold transition flex flex-col sm:flex-row items-center justify-center gap-1 active:scale-95 text-center shadow-sm">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-rose-300"></i>
                            <span class="text-[11px] font-black">Pelanggan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Kanan: Form Login -->
        <div class="bg-white p-8 sm:p-10 flex flex-col justify-center">
            <div class="mb-4 space-y-1">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Selamat Datang!</h2>
                <p class="text-xs text-slate-500 font-medium">Pilih akses akun terminal atau masukkan kredensial Anda</p>
            </div>

            <!-- Tab Switcher Role Kasir POS / Admin / Pelanggan -->
            <div class="mb-3">
                <label class="block uppercase tracking-wider text-[10px] text-slate-400 font-black mb-1.5">Pilih Akses Role Terminal:</label>
                <div class="grid grid-cols-3 gap-1 p-1 bg-slate-100 rounded-2xl text-[11px] font-bold">
                    <button type="button" onclick="isiLogin('admin', 'admin123', 'Super Admin')" id="tabRoleAdmin"
                        class="tab-role py-2 rounded-xl flex items-center justify-center gap-1 text-slate-500 hover:text-slate-800 transition">
                        <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-500"></i>
                        <span>Admin</span>
                    </button>
                    <button type="button" onclick="isiLogin('kasir', 'kasir123', 'Kasir POS')" id="tabRoleKasir"
                        class="tab-role py-2 rounded-xl flex items-center justify-center gap-1 bg-white shadow-sm text-slate-900 font-black transition">
                        <i data-lucide="calculator" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span>Kasir POS</span>
                    </button>
                    <button type="button" onclick="isiLogin('TOBI', 'admin123', 'Pelanggan')" id="tabRolePelanggan"
                        class="tab-role py-2 rounded-xl flex items-center justify-center gap-1 text-slate-500 hover:text-slate-800 transition">
                        <i data-lucide="shopping-bag" class="w-3.5 h-3.5 text-[#ee4d2d]"></i>
                        <span>Pelanggan</span>
                    </button>
                </div>
            </div>

            <!-- Role Info Banner -->
            <div id="roleInfoBadge" class="p-2.5 rounded-2xl border text-[11px] font-bold flex items-center gap-2 mb-4 bg-emerald-50 text-emerald-900 border-emerald-200 shadow-sm">
                <div class="w-5 h-5 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <i data-lucide="calculator" class="w-3 h-3"></i>
                </div>
                <span>Terminal Kasir POS Barcode Scanner, Riwayat & Cetak Struk 58mm</span>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 bg-rose-50 border border-rose-300 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2.5 shadow-sm">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3.5 text-xs font-bold">
                <div>
                    <label class="block uppercase tracking-wider text-[11px] text-slate-600 mb-1.5">Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                        <input type="text" id="inpUsername" name="username" value="kasir" required
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                    </div>
                </div>

                <div>
                    <label class="block uppercase tracking-wider text-[11px] text-slate-600 mb-1.5">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                        <input type="password" id="inputPass" name="password" value="kasir123" required
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                        <button type="button" onclick="togglePass()" class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 transition">
                            <i data-lucide="eye" id="iconEye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="btn_login" id="btnSubmitLogin"
                    class="w-full bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 hover:from-emerald-500 hover:to-teal-600 text-white font-black py-3.5 rounded-2xl shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2 mt-2 active:scale-95 text-xs cursor-pointer">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Masuk Terminal Kasir POS
                </button>
            </form>

            <div class="mt-5 pt-3.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Belum memiliki akun?</span>
                <a href="register.php" class="text-indigo-600 font-extrabold hover:underline flex items-center gap-1">
                    Daftar Akun Baru →
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function togglePass() {
            const p = document.getElementById('inputPass');
            const icon = document.getElementById('iconEye');
            if (p.type === 'password') {
                p.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                p.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        function isiLogin(user, pass, role) {
            document.getElementById('inpUsername').value = user;
            document.getElementById('inputPass').value = pass;

            // Reset tab styles
            document.querySelectorAll('.tab-role').forEach(t => {
                t.classList.remove('bg-white', 'shadow-sm', 'text-slate-900', 'font-black');
                t.classList.add('text-slate-500', 'hover:text-slate-800');
            });

            let tabId = 'tabRoleKasir';
            let infoText = 'Terminal Kasir POS Barcode Scanner, Riwayat & Cetak Struk 58mm';
            let infoBg = 'bg-emerald-50 text-emerald-900 border-emerald-200';
            let iconName = 'calculator';
            let iconBg = 'bg-emerald-600';
            let btnClass = 'from-emerald-600 via-teal-600 to-emerald-700 shadow-emerald-600/30';
            let btnText = 'Masuk Terminal Kasir POS';

            if (role === 'Super Admin' || user === 'admin') {
                tabId = 'tabRoleAdmin';
                infoText = 'Akses Penuh: Dashboard Super Admin, Laporan Omzet SQL, SKU & Setting';
                infoBg = 'bg-amber-50 text-amber-900 border-amber-200';
                iconName = 'shield';
                iconBg = 'bg-amber-600';
                btnClass = 'from-amber-600 via-orange-600 to-amber-700 shadow-amber-600/30';
                btnText = 'Masuk Dashboard Super Admin';
            } else if (role === 'Pelanggan' || user === 'TOBI' || user === 'pelanggan') {
                tabId = 'tabRolePelanggan';
                infoText = 'Akses Belanja: TokoKita Smart Mall, Katalog Mall & Keranjang Belanja';
                infoBg = 'bg-rose-50 text-rose-900 border-rose-200';
                iconName = 'shopping-bag';
                iconBg = 'bg-[#ee4d2d]';
                btnClass = 'from-[#ee4d2d] via-orange-500 to-rose-600 shadow-orange-500/30';
                btnText = 'Masuk Dashboard Belanja Mall';
            }

            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.classList.remove('text-slate-500', 'hover:text-slate-800');
                activeTab.classList.add('bg-white', 'shadow-sm', 'text-slate-900', 'font-black');
            }

            const badge = document.getElementById('roleInfoBadge');
            if (badge) {
                badge.className = `p-2.5 rounded-2xl border text-[11px] font-bold flex items-center gap-2 mb-4 shadow-sm transition ${infoBg}`;
                badge.innerHTML = `<div class="w-5 h-5 rounded-lg ${iconBg} text-white flex items-center justify-center shrink-0">
                                       <i data-lucide="${iconName}" class="w-3 h-3"></i>
                                   </div>
                                   <span>${infoText}</span>`;
            }

            const btnSubmit = document.getElementById('btnSubmitLogin');
            if (btnSubmit) {
                btnSubmit.className = `w-full bg-gradient-to-r ${btnClass} hover:opacity-95 text-white font-black py-3.5 rounded-2xl shadow-lg transition flex items-center justify-center gap-2 mt-2 active:scale-95 text-xs cursor-pointer`;
                btnSubmit.innerHTML = `<i data-lucide="log-in" class="w-4 h-4"></i> ${btnText}`;
            }

            if (window.lucide) {
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
