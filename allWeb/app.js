const body = document.body;
const contentArea = document.getElementById('contentArea');

// Ambil peran dari localStorage
let currentUserRole = localStorage.getItem('currentUserRole');

const menuItems = [
    { id: 'Landing-Page', title: 'Landing Page', icon: 'fas fa-globe', description: 'Akses Landing Page Perusahaan ini.', url: 'http://supracor.co.id', allowedRoles: ['admin', 'production', 'guest', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'] },
    { id: 'Master-Gambar', title: 'Master Gambar', icon: 'fas fa-images', description: 'Kelola, unggah, dan atur semua gambar produk.', url: 'http://edp2:8081/image-search/', allowedRoles: ['admin', 'production', 'guest', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'] },
    { id: 'Sales-Contract', title: 'Sales Contract', icon: 'fas fa-file-contract', description: 'Sistem Sales Contract.', url: 'https://sc.supracor.co.id', allowedRoles: ['admin', 'production', 'guest', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'] },
    { id: 'MCList', title: 'Production Order Dashboard', icon: 'fas fa-industry', description: 'Lacak dan kelola semua pesanan produksi.', url: 'http://edp2:8081/mclist97-v3.html', allowedRoles: ['admin', 'production', 'ppic', 'qc', 'operator'] },
    { id: 'Intake-OP', title: 'Intake OP', icon: 'fas fa-clipboard-list', description: 'Dashboard Intake Order Production.', url: 'http://edp2:8081/intake_op/c/a', allowedRoles: ['admin', 'production', 'ppic', 'qc', 'operator'] },
    { id: 'Label-OP', title: 'Label OP', icon: 'fas fa-file-alt', description: 'Akses laporan produksi eksternal.', url: 'http://edp2:8081/Label_supracor/', allowedRoles: ['admin', 'production', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'] },
    { id: 'List-Warna', title: 'List Warna', icon: 'fas fa-palette', description: 'Manajemen data master untuk semua varian warna.', url: 'http://edp2:8081/listwarna/', allowedRoles: ['admin'] },
    { id: 'Master-Card', title: 'Master Card', icon: 'fas fa-id-card', description: 'Kelola data master untuk kartu produksi.', url: 'http://edp2:8081/listmc/', allowedRoles: ['admin'] },
    { id: 'QR-Generator', title: 'QR Code Generator', icon: 'fas fa-qrcode', description: 'Buat Qr Code mu Sendiri', url: 'https://qr.supracor.co.id', allowedRoles: ['admin', 'production', 'guest', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'] },
];

// FUNGSI 1: MENGGANTI TEMA
function setTheme(theme) {
    body.className = theme;
    localStorage.setItem('dashboardTheme', theme);
    updateActiveThemeButton(theme);
}

function updateActiveThemeButton(activeTheme) {
    document.querySelectorAll('.theme-toggle button').forEach(button => {
        button.style.border = '2px solid transparent';
    });
    const activeButton = document.querySelector(`.theme-toggle button[onclick="setTheme('${activeTheme}')"]`);
    if (activeButton) {
        activeButton.style.border = '2px solid var(--color-accent)';
    }
}

function showMainMenu() {
    document.querySelector('.header h1').textContent = 'Dashboard Website PT Supracor Sejahtera';
    let html = `
        <h2>Selamat Datang, ${currentUserRole.charAt(0).toUpperCase() + currentUserRole.slice(1)}!</h2>
        <p style="margin-bottom: 30px; color: var(--color-text-secondary);">Pilih modul di bawah untuk memulai.</p>
        <div class="menu-grid">
    `;

    const accessibleItems = menuItems.filter(item => item.allowedRoles.includes(currentUserRole));

    if (accessibleItems.length === 0) {
        html += `<p>Tidak ada menu yang tersedia untuk peran Anda.</p>`;
    } else {
        accessibleItems.forEach(item => {
            if (item.url) {
                html += `
                    <a href="${item.url}" target="_blank" rel="noopener noreferrer" class="menu-card" data-menu-id="${item.id}">
                        <i class="${item.icon}"></i>
                        <h3>${item.title}</h3>
                        <p>${item.description}</p>
                    </a>
                `;
            } else {
                html += `
                    <a href="#" class="menu-card" data-menu-id="${item.id}" onclick="showDetail('${item.id}', '${item.title}', event)">
                        <i class="${item.icon}"></i>
                        <h3>${item.title}</h3>
                        <p>${item.description}</p>
                    </a>
                `;
            }
        });
    }

    html += '</div>';
    contentArea.innerHTML = html;
}

// FUNGSI 3: MENAMPILKAN DETAIL KONTEN (SIMULASI LINK)
function showDetail(id, title, event) {
    event.preventDefault(); // Mencegah link pindah halaman

    contentArea.innerHTML = `
        <button class="back-button" onclick="showMainMenu()">
            <i class="fas fa-arrow-left"></i> Kembali ke Menu Utama
        </button>
        <div class="detail-card">
            <h2 style="color: var(--color-accent); margin-bottom: 15px;">${title}</h2>
            <p style="color: var(--color-text-secondary); margin-bottom: 20px;">Ini adalah halaman detail untuk modul **${title}**.</p>
            
            <div style="padding: 20px; border: 1px dashed var(--color-text-secondary); border-radius: 8px;">
                <h4 style="margin-bottom: 10px;">Data Simulasi:</h4>
                <p>Di sini akan dimuat data, grafik, dan tabel spesifik terkait ${title} menggunakan AJAX/Fetch API dari server.</p>
                <p style="margin-top: 10px; font-size: 0.9rem;">(Animasi Loading dan Transition dapat ditambahkan saat konten dimuat.)</p>
            </div>
        </div>
    `;
    // Mengubah judul header untuk mencerminkan halaman saat ini
    document.querySelector('.header h1').textContent = title;
}

// FUNGSI LOGOUT
function logout() {
    localStorage.removeItem('currentUserRole');
    window.location.href = 'login.html';
}

// FUNGSI INISIALISASI
document.addEventListener('DOMContentLoaded', () => {
    // 1. Cek apakah pengguna sudah login
    const validRoles = ['admin', 'production', 'guest', 'ppic', 'marketing', 'purchasing', 'hrd', 'qc', 'operator'];
    if (!currentUserRole || !validRoles.includes(currentUserRole)) {
        window.location.href = 'login.html';
        return;
    }

    // 2. Jika login valid, lanjutkan memuat halaman
    const savedTheme = localStorage.getItem('dashboardTheme') || 'dark-mode';
    setTheme(savedTheme);

    // Tampilkan Menu Utama sesuai peran
    showMainMenu();
});