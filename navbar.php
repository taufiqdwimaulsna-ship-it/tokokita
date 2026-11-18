<?php
$aktif = basename($_SERVER['PHP_SELF']);
$jml_keranjang_nav = isset($_SESSION['keranjang']) ? count($_SESSION['keranjang']) : 0;

if (!function_exists('navItem')) {
    function navItem($file, $label, $icon, $aktif, $badge = 0) {
        $baseFile = explode('?', $file)[0];
        $param    = strpos($file, '?') !== false ? explode('?', $file)[1] : '';
        
        $isActive = false;
        if ($file === $aktif) {
            $isActive = true;
        } elseif ($baseFile === $aktif && empty($param) && empty($_SERVER['QUERY_STRING'])) {
            $isActive = true;
        } elseif (!empty($param) && isset($_SERVER['QUERY_STRING']) && strpos(strtolower($_SERVER['QUERY_STRING']), strtolower($param)) !== false) {
            $isActive = true;
        }

        $isSpecial = ($file === 'kasir_pos.php' || strpos($file, 'keranjang.php') !== false);
        
        if ($isActive) {
            $cls = "bg-gradient-to-r from-[#ee4d2d] to-orange-500 text-white font-black shadow-lg shadow-[#ee4d2d]/30 ring-1 ring-white/20";
        } elseif ($isSpecial) {
            $cls = "bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 font-bold";
        } else {
            $cls = "text-slate-300 hover:bg-slate-800/90 hover:text-white";
        }

        $badgeHtml = "";
        if ($badge > 0) {
            $badgeHtml = "<span class='ml-1 px-1.5 py-0.5 text-[9px] font-black rounded-full bg-rose-500 text-white animate-pulse'>$badge</span>";
        }

        return "<a href='$file' class='px-3 py-1.5 rounded-xl flex items-center gap-1.5 text-xs font-semibold transition-all duration-150 shrink-0 hover:scale-[1.02] active:scale-[0.98] whitespace-nowrap $cls'>
                    <i data-lucide='$icon' class='w-3.5 h-3.5 " . ($isActive ? "text-white" : ($isSpecial ? "text-emerald-400" : "text-slate-400")) . "'></i>
                    <span>$label</span>
                    $badgeHtml
                </a>";
    }
}

$user_nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'User';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Kasir POS';
$is_pelanggan = function_exists('isPelanggan') ? isPelanggan() : (strtolower(trim($user_role)) === 'pelanggan' || strtolower(trim($user_role)) === 'customer');
$is_kasir     = function_exists('isKasir') ? isKasir() : (strtolower(trim($user_role)) === 'kasir' || strtolower(trim($user_role)) === 'kasir pos');
$is_admin     = function_exists('isAdmin') ? isAdmin() : (!$is_pelanggan && !$is_kasir);
?>
<!-- Google Font: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        letter-spacing: -0.01em;
    }
    .custom-scrollbar::-webkit-scrollbar {
        height: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 4px;
    }
</style>

<header class="bg-slate-950/90 backdrop-blur-xl text-white border-b border-slate-800/80 sticky top-0 z-50 print:hidden shadow-2xl">
    <div class="max-w-7xl mx-auto px-4 py-2.5">
        <!-- Baris 1: Brand Logo, Jam Realtime & User Status -->
        <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-800/70">
            <!-- Brand Logo Baru TokoKita (Modern 3D Mall & POS Brandmark) -->
            <a href="<?= $is_pelanggan ? 'index.php' : 'dashboard.php' ?>" class="flex items-center gap-3 group shrink-0">
                <!-- Glowing Emblem Container -->
                <div class="relative flex items-center justify-center">
                    <div class="absolute -inset-1 bg-gradient-to-r from-[#ee4d2d] via-amber-400 to-indigo-500 rounded-2xl blur-sm opacity-60 group-hover:opacity-100 transition duration-300"></div>
                    <div class="relative w-10 h-10 rounded-2xl bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 border border-white/20 flex items-center justify-center shadow-xl overflow-hidden">
                        <!-- Custom SVG Vector Logo TokoKita -->
                        <svg class="w-6 h-6 transform group-hover:scale-110 group-hover:-rotate-3 transition duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="tkBagGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#FF5722"/>
                                    <stop offset="45%" stop-color="#EE4D2D"/>
                                    <stop offset="100%" stop-color="#C2185B"/>
                                </linearGradient>
                                <linearGradient id="tkHandleGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#FFE082"/>
                                    <stop offset="100%" stop-color="#FFA000"/>
                                </linearGradient>
                            </defs>
                            <!-- Handle Pegangan Tas -->
                            <path d="M16 15C16 10.58 19.58 7 24 7C28.42 7 32 10.58 32 15" stroke="url(#tkHandleGrad)" stroke-width="3.5" stroke-linecap="round"/>
                            <!-- Badan Tas Belanja -->
                            <rect x="7" y="14" width="34" height="27" rx="7" fill="url(#tkBagGrad)"/>
                            <!-- Garis Lipatan / Kilau Geometris -->
                            <path d="M8 21L24 28L40 21" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.4"/>
                            <!-- Bintang Loyalitas Emas TokoKita -->
                            <path d="M24 19.5L25.8 24.2L30.5 24.8L27.1 28.1L27.9 32.8L24 30.4L20.1 32.8L20.9 28.1L17.5 24.8L22.2 24.2L24 19.5Z" fill="#FFF" fill-opacity="0.95"/>
                        </svg>
                    </div>
                </div>

                <!-- Brand Typography -->
                <div class="flex flex-col">
                    <div class="flex items-center gap-1.5">
                        <span class="font-black text-base sm:text-lg tracking-tight text-white flex items-center leading-none">
                            <span>Toko</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-[#ee4d2d] via-rose-400 to-amber-300">Kita</span>
                        </span>
                        <span class="inline-flex items-center gap-1 bg-gradient-to-r from-rose-500/20 to-orange-500/20 text-rose-300 border border-rose-500/30 text-[9.5px] font-black px-1.5 py-0.5 rounded-md uppercase tracking-wider shadow-sm">
                            <svg class="w-2.5 h-2.5 text-amber-400 fill-amber-400" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?= $is_pelanggan ? 'Official Mall' : 'POS 2.0' ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] text-slate-400 leading-none mt-1">
                        <span>Smart Retail & Inventory</span>
                        <span class="w-1 h-1 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-emerald-400 font-bold text-[9px]">Live</span>
                    </div>
                </div>
            </a>

            <!-- Jam Realtime & User Info -->
            <div class="flex items-center gap-3">
                <!-- Jam Digital Realtime -->
                <div class="hidden md:flex items-center gap-1.5 bg-slate-900/90 border border-slate-800 px-3 py-1 rounded-xl text-[11px] font-mono text-slate-300 shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-indigo-400"></i>
                    <span id="jamDigitalNavbar">--:--:-- WIB</span>
                </div>

                <!-- User Badge / Link Profil -->
                <a href="profil.php" class="flex items-center gap-2 bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 px-2.5 py-1 rounded-xl text-xs shadow-sm transition group" title="Buka Profil Saya">
                    <div class="relative">
                        <div class="w-7 h-7 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center font-black text-xs shadow-md shadow-indigo-600/30 group-hover:scale-105 transition">
                            <?= strtoupper(substr($user_nama, 0, 1)) ?>
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>
                    </div>
                    <div class="text-left hidden sm:block">
                        <p class="font-bold text-slate-200 text-xs leading-none truncate max-w-[120px] group-hover:text-indigo-200 transition"><?= htmlspecialchars($user_nama) ?></p>
                        <span class="text-[9px] font-extrabold uppercase tracking-wide <?= $is_pelanggan ? 'text-amber-400' : ($is_kasir ? 'text-emerald-400' : 'text-indigo-300') ?>">
                            <?= htmlspecialchars($user_role) ?>
                        </span>
                    </div>
                </a>

                <!-- Tombol Logout -->
                <a href="logout.php" onclick="return confirm('Keluar dari sistem TokoKita?')" 
                   class="bg-rose-500/10 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/20 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm active:scale-95" title="Logout">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span class="hidden md:inline">Keluar</span>
                </a>
            </div>
        </div>

        <!-- Baris 2: Navigasi Menu Horizontal -->
        <div class="relative flex items-center pt-2 gap-1.5 w-full">
            <?php if ($is_admin): ?>
                <!-- Tombol Scroll Kiri (Khusus Administrator dengan 15 Menu) -->
                <button type="button" onclick="scrollNavToko('left')" id="btnNavScrollKiri"
                    class="bg-slate-900 hover:bg-[#ee4d2d] text-slate-300 hover:text-white p-2 rounded-xl border border-slate-700 hover:border-[#ee4d2d] shadow-lg transition-all duration-200 shrink-0 active:scale-75 flex items-center justify-center z-20 cursor-pointer" 
                    title="Geser menu ke kiri">
                    <i data-lucide="chevron-left" class="w-4 h-4 pointer-events-none"></i>
                </button>
            <?php endif; ?>

            <!-- Navigasi Menu Horizontal -->
            <nav id="menuNavTokoKita" class="flex-1 min-w-0 flex items-center gap-2 overflow-x-auto scroll-smooth custom-scrollbar py-0.5">
                <?php if ($is_pelanggan): ?>
                    <!-- HAK AKSES KHUSUS PELANGGAN: DASHBOARD, KATALOG, KERANJANG, ISI SALDO, DAN PROFIL -->
                    <?= navItem('index.php', 'Dashboard Belanja', 'layout-dashboard', $aktif) ?>
                    <?= navItem('kategori.php', 'Katalog Produk', 'store', $aktif) ?>
                    <?= navItem('keranjang.php', 'Keranjang Belanja', 'shopping-cart', $aktif, $jml_keranjang_nav) ?>
                    <?= navItem('saldo.php', 'Isi Saldo Member', 'wallet', $aktif) ?>
                    <?= navItem('profil.php', 'Profil Saya', 'user', $aktif) ?>
                <?php elseif ($is_kasir): ?>
                    <!-- HAK AKSES KHUSUS KASIR POS: DASHBOARD, SCANNER, PRODUK, MEMBER PELANGGAN, PROFIL -->
                    <?= navItem('dashboard.php', 'Dashboard', 'layout-dashboard', $aktif) ?>
                    <?= navItem('kasir_pos.php', 'Scanner', 'scan-barcode', $aktif) ?>
                    <?= navItem('admin_produk.php', 'Produk', 'package', $aktif) ?>
                    <?= navItem('pelanggan.php', 'Member Pelanggan', 'users', $aktif) ?>
                    <?= navItem('profil.php', 'Profil', 'user', $aktif) ?>
                <?php else: ?>
                    <!-- HAK AKSES LENGKAP ADMINISTRATOR (15 Menu Utama) -->
                    <?= navItem('dashboard.php', 'Dashboard', 'layout-dashboard', $aktif) ?>
                    <?= navItem('kasir_pos.php', 'Scanner Barcode POS', 'scan-barcode', $aktif) ?>
                    <?= navItem('kasir_pesanan.php', 'Antrean', 'clock', $aktif) ?>
                    <?= navItem('kasir_riwayat.php', 'Riwayat Kasir', 'history', $aktif) ?>
                    <?= navItem('struk.php', 'Struk 58mm', 'receipt', $aktif) ?>
                    <?= navItem('admin_produk.php', 'Produk SKU', 'package', $aktif) ?>
                    <?= navItem('kategori.php', 'Kategori', 'layers', $aktif) ?>
                    <?= navItem('pelanggan.php', 'Member Pelanggan', 'users', $aktif) ?>
                    <?= navItem('saldo.php', 'Dompet Saldo', 'wallet-cards', $aktif) ?>
                    <?= navItem('diskon.php', 'Diskon', 'percent', $aktif) ?>
                    <?= navItem('favorit.php', 'Favorit', 'heart', $aktif) ?>
                    <?= navItem('checkout.php', 'Checkout', 'credit-card', $aktif) ?>
                    <?= navItem('aktivitas.php', 'Log Trigger', 'activity', $aktif) ?>
                    <?= navItem('laporan.php', 'Laporan SQL', 'file-bar-chart', $aktif) ?>
                    <?= navItem('profil.php', 'Profil', 'user', $aktif) ?>
                <?php endif; ?>
            </nav>

            <?php if ($is_admin): ?>
                <!-- Tombol Scroll Kanan (Khusus Administrator dengan 15 Menu) -->
                <button type="button" onclick="scrollNavToko('right')" id="btnNavScrollKanan"
                    class="bg-slate-900 hover:bg-[#ee4d2d] text-slate-300 hover:text-white p-2 rounded-xl border border-slate-700 hover:border-[#ee4d2d] shadow-lg transition-all duration-200 shrink-0 active:scale-75 flex items-center justify-center z-20 cursor-pointer" 
                    title="Geser menu ke kanan">
                    <i data-lucide="chevron-right" class="w-4 h-4 pointer-events-none"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    function updateJamNavbar() {
        const jamEl = document.getElementById('jamDigitalNavbar');
        if (jamEl) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            jamEl.innerText = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' WIB';
        }
    }
    setInterval(updateJamNavbar, 1000);
    updateJamNavbar();

    // Fungsi Scroll Kiri / Kanan Horizontal Murni (Untuk Navigasi Admin / POS)
    function scrollNavToko(direction) {
        const nav = document.getElementById('menuNavTokoKita');
        if (!nav) return;

        const btn = direction === 'left' ? document.getElementById('btnNavScrollKiri') : document.getElementById('btnNavScrollKanan');
        if (btn) {
            btn.classList.add('ring-2', 'ring-[#ee4d2d]', 'scale-90');
            setTimeout(() => btn.classList.remove('ring-2', 'ring-[#ee4d2d]', 'scale-90'), 150);
        }

        const scrollStep = 320;
        const maxScroll = nav.scrollWidth - nav.clientWidth;

        if (maxScroll <= 5) {
            nav.style.transition = 'transform 0.18s ease';
            nav.style.transform = direction === 'left' ? 'translateX(16px)' : 'translateX(-16px)';
            setTimeout(() => { nav.style.transform = 'translateX(0px)'; }, 180);
            return;
        }

        if (direction === 'left') {
            if (nav.scrollLeft <= 10) {
                nav.scrollTo({ left: nav.scrollWidth, behavior: 'smooth' });
            } else {
                nav.scrollBy({ left: -scrollStep, behavior: 'smooth' });
            }
        } else {
            if (nav.scrollLeft >= maxScroll - 15) {
                nav.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                nav.scrollBy({ left: scrollStep, behavior: 'smooth' });
            }
        }
    }

    // Dukungan mouse wheel & auto-scroll ke menu aktif
    document.addEventListener('DOMContentLoaded', () => {
        const nav = document.getElementById('menuNavTokoKita');
        if (nav) {
            // Scroll horizontal dengan mouse wheel
            nav.addEventListener('wheel', (e) => {
                if (e.deltaY !== 0) {
                    e.preventDefault();
                    nav.scrollBy({ left: e.deltaY * 1.5, behavior: 'smooth' });
                }
            }, { passive: false });

            // Otomatis geser agar menu aktif terlihat jika berada di luar layar
            setTimeout(() => {
                if (window.lucide) {
                    lucide.createIcons();
                }
                const activeItem = nav.querySelector('.shadow-lg') || nav.querySelector('.font-black');
                if (activeItem) {
                    const navRect = nav.getBoundingClientRect();
                    const itemRect = activeItem.getBoundingClientRect();
                    if (itemRect.left < navRect.left || itemRect.right > navRect.right) {
                        activeItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    }
                }
            }, 100);
        }
    });
</script>
