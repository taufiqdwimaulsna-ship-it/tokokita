<?php
$aktif = basename($_SERVER['PHP_SELF']);
if (!function_exists('navItem')) {
    function navItem($file, $label, $icon, $aktif) {
        $isActive = ($file === $aktif);
        $cls = $isActive
            ? "bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/30 scale-[1.02]"
            : "text-slate-300 hover:bg-slate-800/80 hover:text-white";
        return "<a href='$file' class='px-3 py-1.5 rounded-xl flex items-center gap-1.5 text-xs font-semibold transition-all duration-150 shrink-0 $cls'>
                    <i data-lucide='$icon' class='w-3.5 h-3.5 " . ($isActive ? "text-white" : "text-slate-400") . "'></i>
                    <span>$label</span>
                </a>";
    }
}

$user_nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'User';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Kasir POS';
$is_pelanggan = function_exists('isPelanggan') ? isPelanggan() : (strtolower(trim($user_role)) === 'pelanggan' || strtolower(trim($user_role)) === 'customer');
?>
<!-- Google Font: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    }
    .custom-scrollbar::-webkit-scrollbar {
        height: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }
</style>

<header class="bg-slate-900/95 backdrop-blur-md text-white border-b border-slate-800 sticky top-0 z-50 print:hidden shadow-xl">
    <div class="max-w-7xl mx-auto px-4 py-2.5">
        <!-- Baris 1: Brand Logo & User Profile / Status -->
        <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-800/70">
            <!-- Brand -->
            <a href="dashboard.php" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-black text-sm tracking-tight text-white group-hover:text-indigo-300 transition">TokoKita</span>
                        <span class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[9px] font-extrabold px-1.5 py-0.2 rounded-md uppercase">POS</span>
                    </div>
                    <p class="text-[10px] text-slate-400 leading-none">Enterprise & Inventory</p>
                </div>
            </a>

            <!-- User Info & Quick Action -->
            <div class="flex items-center gap-2.5">
                <div class="hidden sm:flex items-center gap-2 bg-slate-800/70 border border-slate-700/60 px-2.5 py-1 rounded-xl text-xs">
                    <div class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-[11px]">
                        <?= strtoupper(substr($user_nama, 0, 1)) ?>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-slate-200 text-xs leading-tight leading-none"><?= htmlspecialchars($user_nama) ?></p>
                        <span class="text-[9px] text-indigo-300 font-semibold"><?= htmlspecialchars($user_role) ?></span>
                    </div>
                </div>

                <a href="logout.php" onclick="return confirm('Keluar dari sistem TokoKita?')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/20 px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1" title="Logout">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span class="hidden md:inline">Keluar</span>
                </a>
            </div>
        </div>

        <!-- Baris 2: Navigasi Menu Horizontal (Scrollable jika layar sempit) -->
        <nav class="flex items-center gap-1 overflow-x-auto pt-2 custom-scrollbar no-scrollbar py-0.5">
            <?php if ($is_pelanggan): ?>
                <!-- HAK AKSES KHUSUS PELANGGAN: HANYA DASHBOARD, KATEGORI, DAN KERANJANG -->
                <?= navItem('index.php', 'Dashboard', 'layout-dashboard', $aktif) ?>
                <?= navItem('kategori.php', 'Kategori & Produk', 'layers', $aktif) ?>
                <?= navItem('keranjang.php', 'Keranjang Belanja', 'shopping-cart', $aktif) ?>
            <?php else: ?>
                <!-- HAK AKSES LENGKAP KASIR POS & ADMINISTRATOR -->
                <?= navItem('dashboard.php', 'Dashboard', 'layout-dashboard', $aktif) ?>
                <?= navItem('kasir_pos.php', 'Kasir POS', 'calculator', $aktif) ?>
                <?= navItem('kasir_pesanan.php', 'Antrean', 'clock', $aktif) ?>
                <?= navItem('kasir_riwayat.php', 'Riwayat Kasir', 'history', $aktif) ?>
                <?= navItem('struk.php', 'Struk 58mm', 'receipt', $aktif) ?>
                <?= navItem('admin_produk.php', 'Produk', 'package', $aktif) ?>
                <?= navItem('saldo.php', 'Dompet Saldo', 'wallet-cards', $aktif) ?>
                <?= navItem('diskon.php', 'Diskon', 'percent', $aktif) ?>
                <?= navItem('favorit.php', 'Favorit', 'heart', $aktif) ?>
                <?= navItem('checkout.php', 'Checkout', 'credit-card', $aktif) ?>
                <?= navItem('aktivitas.php', 'Log Trigger', 'activity', $aktif) ?>
                <?= navItem('laporan.php', 'Laporan SQL', 'file-bar-chart', $aktif) ?>
                <?= navItem('profil.php', 'Profil', 'user', $aktif) ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
