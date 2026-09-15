<?php
/**
 * pegawai/_profil_helper.php
 * Helper bersama untuk halaman Kartu Pegawai & Profil Kehadiran.
 * Semua fungsi diberi awalan pf_ agar tidak bentrok dengan fungsi lain.
 */

/* ---------- 6 kategori presensi & absensi (urutan tampil) ---------- */
$PF_KATEGORI = [
    'tepat'     => ['label' => 'Tepat Waktu', 'icon' => 'bi-check-circle',     'warna' => '#2eca6a', 'tint' => '#e0f8e9'],
    'terlambat' => ['label' => 'Terlambat',   'icon' => 'bi-clock-history',    'warna' => '#ff9800', 'tint' => '#fff3e0'],
    'izin'      => ['label' => 'Izin',        'icon' => 'bi-envelope-paper',   'warna' => '#4154f1', 'tint' => '#e8ebfe'],
    'sakit'     => ['label' => 'Sakit',       'icon' => 'bi-bandaid',          'warna' => '#0aa2c0', 'tint' => '#dff5f9'],
    'cuti'      => ['label' => 'Cuti',        'icon' => 'bi-sun',              'warna' => '#8e44ad', 'tint' => '#f2e7f7'],
    'alfa'      => ['label' => 'Alfa',        'icon' => 'bi-x-circle',         'warna' => '#e74c3c', 'tint' => '#fde0e0'],
];

if (!function_exists('pf_h')) {

/** escape aman */
function pf_h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** TIME/DateTime -> menit sejak 00:00 (null bila kosong) */
function pf_menit($v) {
    if ($v instanceof DateTimeInterface) return (int)$v->format('H') * 60 + (int)$v->format('i');
    if (is_string($v) && preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) return (int)$m[1] * 60 + (int)$m[2];
    return null;
}

/** tampilkan jam H:i */
function pf_jam($v) {
    $m = pf_menit($v);
    return $m === null ? '—' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/** tampilkan tanggal */
function pf_tgl($v, $fmt = 'd M Y') {
    if (!$v instanceof DateTimeInterface) return '—';
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $out = $v->format($fmt);
    return str_replace($v->format('M'), $bulan[(int)$v->format('n') - 1], $out);
}

/**
 * Menit terlambat terhadap jam mulai shift.
 * Menangani shift lintas tengah malam: tap 21:00-23:59 utk shift 00:00 = datang awal.
 */
function pf_menit_telat(array $r): int {
    $masuk = pf_menit($r['jam_masuk'] ?? null);
    $mulai = pf_menit($r['jam_mulai'] ?? null);
    if ($masuk === null || $mulai === null) return 0;
    $d = $masuk - $mulai;
    if ($d > 720)  $d -= 1440;
    if ($d < -720) $d += 1440;
    return max(0, $d);
}

/**
 * Tentukan kategori 1 baris absensi.
 * $pakaiToleransi = true  -> ikut status resmi HR (sudah pakai toleransi shift)
 * $pakaiToleransi = false -> hitung ketat: lewat jam mulai shift = terlambat
 * (sama persis dengan logika Dashboard Presensi)
 */
function pf_kategori(array $r, bool $pakaiToleransi): string {
    $s = strtolower(trim((string)($r['status'] ?? '')));
    if (in_array($s, ['izin', 'sakit', 'cuti'], true)) return $s;
    if (in_array($s, ['alpha', 'alfa', 'alpa'], true))  return 'alfa';
    if ($pakaiToleransi) return $s === 'terlambat' ? 'terlambat' : 'tepat';
    return pf_menit_telat($r) > 0 ? 'terlambat' : 'tepat';
}

/** hitung jumlah per kategori dari kumpulan baris */
function pf_hitung(array $rows, bool $pakaiToleransi): array {
    $n = ['tepat' => 0, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alfa' => 0];
    foreach ($rows as $r) $n[pf_kategori($r, $pakaiToleransi)]++;
    return $n;
}

/** inisial nama untuk avatar cadangan */
function pf_inisial($nama) {
    $kata = preg_split('/\s+/u', trim((string)$nama)) ?: [];
    $huruf = [];
    foreach ($kata as $k) {
        if (!preg_match('/\p{L}|\p{N}/u', $k, $m)) continue;   // huruf pertama (aman UTF-8)
        $huruf[] = function_exists('mb_strtoupper') ? mb_strtoupper($m[0], 'UTF-8') : strtoupper($m[0]);
        if (count($huruf) >= 2) break;
    }
    return $huruf ? implode('', $huruf) : '?';
}

/** warna avatar stabil berdasarkan nama (palet tema) */
function pf_warna_avatar($nama) {
    $palet = ['#4154f1', '#012970', '#2eca6a', '#ff771d', '#0aa2c0', '#8e44ad'];
    return $palet[abs(crc32((string)$nama)) % count($palet)];
}

/** masa kerja "x th y bln" */
function pf_masa_kerja($tglMasuk, $tglAkhir = null) {
    if (!$tglMasuk instanceof DateTimeInterface) return '—';
    $akhir = $tglAkhir instanceof DateTimeInterface ? $tglAkhir : new DateTime();
    $d = $tglMasuk->diff($akhir);
    if ($d->invert) return '—';
    $out = [];
    if ($d->y) $out[] = $d->y . ' th';
    if ($d->m) $out[] = $d->m . ' bln';
    return $out ? implode(' ', $out) : $d->d . ' hari';
}

/** warna badge status karyawan (sama dgn pegawai/index.php) */
function pf_badge_status($s) {
    return ['tetap' => 'success', 'kontrak' => 'warning text-dark', 'harian' => 'secondary'][strtolower((string)$s)] ?? 'light';
}

/** validasi tanggal Y-m-d, kembalikan string atau default */
function pf_tanggal_valid($v, $default) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
}

/** avatar HTML: foto bila ada, jatuh ke inisial bila gagal dimuat */
function pf_avatar_html($foto, $nama, $kelas = 'pf-avatar') {
    $ini   = pf_h(pf_inisial($nama));
    $warna = pf_warna_avatar($nama);
    $html  = '<div class="' . $kelas . '" style="--pf-av:' . $warna . '"><span>' . $ini . '</span>';
    if (!empty($foto)) {
        $html .= '<img src="' . pf_h($foto) . '" alt="Foto ' . pf_h($nama) . '" loading="lazy" onerror="this.remove()">';
    }
    return $html . '</div>';
}

}
