/**
 * TokoKita - Client Side JavaScript Helper
 * File: script.js
 * Deskripsi: Utilitas interaktif untuk POS Kasir, format rupiah, filter tabel,
 * kalkulasi transaksi, modal, dan cetak struk.
 */

// Inisialisasi otomatis icon Lucide saat DOM selesai dimuat
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

/**
 * Memperbarui render icon Lucide (berguna setelah manipulasi DOM)
 */
function refreshIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Format angka ke format mata uang Rupiah
 * Contoh: 50000 -> "Rp 50.000"
 */
function formatRupiah(angka, prefix = 'Rp ') {
    if (angka === null || angka === undefined || isNaN(angka)) return prefix + '0';
    const numberString = Math.round(angka).toString();
    const sisa = numberString.length % 3;
    let rupiah = numberString.substr(0, sisa);
    const ribuan = numberString.substr(sisa).match(/\d{3}/g);

    if (ribuan) {
        const separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    return prefix + rupiah;
}

/**
 * Mengubah string rupiah menjadi integer/float murni
 * Contoh: "Rp 50.000" -> 50000
 */
function parseRupiah(rupiahString) {
    if (!rupiahString) return 0;
    const clean = rupiahString.toString().replace(/[^0-9,-]/g, '').replace(',', '.');
    return parseFloat(clean) || 0;
}

/**
 * Filter tabel realtime berdasarkan input pencarian
 * @param {string} inputId - ID elemen input teks pencarian
 * @param {string} tableId - ID elemen tabel HTML
 */
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let matched = false;

        for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent || cells[j].innerText;
            if (cellText.toLowerCase().indexOf(filter) > -1) {
                matched = true;
                break;
            }
        }
        row.style.display = matched ? '' : 'none';
    }
}

/**
 * Filter elemen kartu / grid (contoh: katalog produk POS)
 * @param {string} inputId - ID input pencarian
 * @param {string} containerId - ID container grid
 * @param {string} itemSelector - Selector item kartu
 */
function filterCardGrid(inputId, containerId, itemSelector = '.product-card') {
    const input = document.getElementById(inputId);
    const container = document.getElementById(containerId);
    if (!input || !container) return;

    const query = input.value.toLowerCase();
    const items = container.querySelectorAll(itemSelector);

    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? '' : 'none';
    });
}

/**
 * Hitung kembalian kasir secara interaktif
 * @param {number} totalBelanja - Total tagihan belanja
 * @param {number} uangBayar - Uang yang diserahkan pelanggan
 * @returns {number} kembalian (atau 0 jika kurang)
 */
function hitungKembalian(totalBelanja, uangBayar) {
    const kembalian = uangBayar - totalBelanja;
    return kembalian > 0 ? kembalian : 0;
}

/**
 * Toggle visibilitas password form (password <-> text)
 * @param {string} inputId - ID input password
 */
function togglePassword(inputId) {
    const el = document.getElementById(inputId);
    if (el) {
        el.type = el.type === 'password' ? 'text' : 'password';
    }
}

/**
 * Salin teks ke papan klip (Clipboard) dengan notifikasi
 * @param {string} text - Teks yang akan disalin
 * @param {string} notifPesan - Pesan notifikasi sukses
 */
function salinKeClipboard(text, notifPesan = 'Berhasil disalin ke clipboard!') {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            tampilkanToast(notifPesan, 'success');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            tampilkanToast(notifPesan, 'success');
        } catch (err) {
            console.error('Gagal menyalin:', err);
        }
        document.body.removeChild(textArea);
    }
}

/**
 * Tampilkan notifikasi toast mengambang
 * @param {string} pesan - Pesan yang akan ditampilkan
 * @param {string} tipe - 'success', 'error', 'info', 'warning'
 */
function tampilkanToast(pesan, tipe = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colorClasses = {
        success: 'bg-emerald-600 text-white',
        error: 'bg-rose-600 text-white',
        warning: 'bg-amber-600 text-white',
        info: 'bg-indigo-600 text-white'
    };

    toast.className = `${colorClasses[tipe] || colorClasses.info} px-4 py-3 rounded-2xl shadow-xl text-xs font-semibold flex items-center gap-2 transform translate-y-4 opacity-0 transition-all duration-300 pointer-events-auto`;
    toast.innerHTML = `<span>${pesan}</span>`;

    container.appendChild(toast);

    // Animasi masuk
    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    }, 10);

    // Otomatis hilang setelah 3.5 detik
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/**
 * Buka modal popup
 * @param {string} modalId - ID elemen modal
 */
function bukaModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

/**
 * Tutup modal popup
 * @param {string} modalId - ID elemen modal
 */
function tutupModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

/**
 * Dialog konfirmasi hapus data
 * @param {string} pesan - Pertanyaan konfirmasi
 * @param {string} urlTujuan - URL tujuan hapus jika disetujui
 */
function konfirmasiHapus(pesan, urlTujuan) {
    if (confirm(pesan || 'Apakah Anda yakin ingin menghapus data ini?')) {
        window.location.href = urlTujuan;
    }
}

/**
 * Print struk atau halaman saat ini
 */
function cetakDokumen() {
    window.print();
}

/**
 * Suara Beep Scanner Kasir Retail menggunakan HTML5 Web Audio API
 */
function playScannerBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1850, audioCtx.currentTime); // Pitch barcode scanner retail 1850 Hz
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch (e) {
        console.warn('AudioContext tidak didukung atau diblokir browser', e);
    }
}

/**
 * Analisis Payload Barcode / QR Pembayaran
 * Mendeteksi jenis e-wallet / metode bayar dari kode barcode
 */
function parsePaymentBarcode(rawText) {
    if (!rawText) return null;
    const clean = rawText.trim();
    const upper = clean.toUpperCase();

    let metode = 'QRIS';
    let label = 'QRIS Instan';

    if (upper.includes('DANA') || upper.startsWith('DANA') || upper.includes('00020101021226590014ID.DANA')) {
        metode = 'DANA';
        label = 'DANA Dompet Digital';
    } else if (upper.includes('SHOPEE') || upper.includes('SPAY') || upper.startsWith('SPAY')) {
        metode = 'ShopeePay';
        label = 'ShopeePay';
    } else if (upper.includes('OVO') || upper.startsWith('OVO')) {
        metode = 'OVO';
        label = 'OVO Payment';
    } else if (upper.includes('BCA') || upper.startsWith('BCA')) {
        metode = 'Transfer BCA';
        label = 'Transfer Bank BCA';
    } else if (upper.includes('BRI') || upper.startsWith('BRI')) {
        metode = 'Transfer BRI';
        label = 'Transfer Bank BRI';
    } else if (upper.includes('MEMBER') || upper.includes('SALDO') || upper.startsWith('MBR-')) {
        metode = 'Saldo TokoKita';
        label = 'Saldo TokoKita Pay';
    } else if (upper.startsWith('000201') || upper.includes('QRIS') || upper.includes('NMID')) {
        metode = 'QRIS';
        label = 'QRIS Nasional (BCA/Dana/Gopay)';
    } else {
        metode = 'QRIS';
        label = 'Barcode E-Wallet (' + clean.substring(0, 15) + ')';
    }

    return {
        raw: clean,
        metode: metode,
        label: label,
        refCode: clean.length > 25 ? clean.substring(0, 20) + '...' : clean
    };
}

