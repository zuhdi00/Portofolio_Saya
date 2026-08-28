<?php
/**
 * pegawai/_normalisasi_enum.php   [v2 - disesuaikan dgn CHECK constraint asli dbHR]
 *
 * Menjembatani nilai dari FORM -> nilai yang diizinkan CHECK constraint dbHR.
 * Kunci peta memakai format "tabel.kolom" karena kolom bernama sama bisa
 * punya aturan berbeda antar tabel (contoh: status_nikah di pegawai vs keluarga).
 */

$PETA_ENUM = [

    /* ================= TABEL pegawai ================= */

    // CHECK: 'L' atau 'P'
    'pegawai.gender' => [
        'M' => 'L',
        'F' => 'P',
    ],

    // CHECK: Islam/Kristen/Katolik/Hindu/Buddha/Konghucu
    // Form sudah mengirim nilai yang sama persis -> tidak perlu konversi.
    'pegawai.agama' => [],

    // CHECK: 'harian' / 'kontrak' / 'tetap'
    // Diturunkan dari dropdown "Employee Subgroup" di form.
    // >>> SESUAIKAN dgn aturan kepegawaian PT Supracor Sejahtera <<<
    'pegawai.status_karyawan' => [
        'Kary. Tetap'                  => 'tetap',
        'Staff'                        => 'tetap',
        'Kary. Harian Kontrak (kls.1)' => 'kontrak',
        'Kary. Harian Kontrak (kls.2)' => 'kontrak',
        'Kary. Borongan'               => 'harian',
    ],

    // CHECK: 'TK' / 'K0' / 'K1' / 'K2' / 'K3'  --> ini KODE PTKP PAJAK,
    // bukan status pernikahan. Diambil dari dropdown "PTKP Status" di form.
    'pegawai.status_nikah' => [
        'Single, 0 Anak (TK/0) - Rp 54,000,000' => 'TK',
        'Kawin, 0 Anak (K/0) - Rp 58,500,000'   => 'K0',
        'Kawin, 1 Anak (K/1) - Rp 63,000,000'   => 'K1',
        'Kawin, 2 Anak (K/2) - Rp 67,500,000'   => 'K2',
        'Kawin, 3 Anak (K/3) - Rp 72,000,000'   => 'K3',
    ],

    /* ================= TABEL keluarga_pegawai ================= */

    // CHECK: 'L' atau 'P'
    'keluarga.gender' => [
        'MALE'   => 'L',
        'FEMALE' => 'P',
    ],

    // CHECK: 'ayah' / 'ibu' / 'anak' / 'pasangan' / 'saudara'
    'keluarga.hubungan' => [
        'SPOUSE'  => 'pasangan',
        'CHILD'   => 'anak',
        'FATHER'  => 'ayah',
        'MOTHER'  => 'ibu',
        'SIBLING' => 'saudara',
    ],

    // CHECK: 'belum' / 'menikah'
    'keluarga.status_nikah' => [
        'SINGLE'   => 'belum',
        'MARRIED'  => 'menikah',
        'DIVORCED' => 'belum',   // cerai -> tidak sedang menikah
        'WIDOWED'  => 'belum',
    ],

    // CHECK: 'hidup' / 'meninggal'
    'keluarga.status_hidup' => [
        'ALIVE'    => 'hidup',
        'DECEASED' => 'meninggal',
    ],

    /* ================= TABEL pendidikan_pegawai ================= */

    // CHECK: SD/SMP/SMA/SMK/D3/S1/S2/S3
    'pendidikan.jenjang' => [
        'SMA/SMK'  => 'SMA',      // form menggabung SMA & SMK jadi satu opsi
        'Strata 1' => 'S1',
        'Strata 2' => 'S2',
        'Strata 3' => 'S3',
    ],

    /* ================= TABEL absensi ================= */

    // CHECK: hadir/terlambat/izin/sakit/cuti/alpha (huruf kecil semua)
    'absensi.status' => [
        'Hadir'     => 'hadir',
        'Terlambat' => 'terlambat',
        'Izin'      => 'izin',
        'Sakit'     => 'sakit',
        'Cuti'      => 'cuti',
        'Alpha'     => 'alpha',
    ],
];

/**
 * Terjemahkan nilai form -> nilai dbHR.
 * @param string $konteks format "tabel.kolom", contoh: 'pegawai.gender'
 * Kalau nilai tidak ada di peta, dikirim apa adanya.
 */
function enum_db($konteks, $nilai) {
    global $PETA_ENUM;
    if ($nilai === null || $nilai === '') return null;
    return $PETA_ENUM[$konteks][$nilai] ?? $nilai;
}
