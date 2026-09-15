<?php
/**
 * pegawai/_normalisasi_enum.php
 *
 * Menjembatani nilai dari FORM  ->  nilai yang diizinkan CHECK constraint dbHR.
 *
 * CARA MENYESUAIKAN:
 * 1. Jalankan sql/02_cek_check_constraint.sql di SSMS.
 * 2. Lihat kolom "nilai_yang_diizinkan", catat nilai persisnya.
 * 3. Edit array di bawah -> sisi KANAN harus SAMA PERSIS dengan nilai di DB
 *    (huruf besar/kecil ikut berpengaruh).
 */

/** Peta konversi: [nilai dari form] => [nilai yang diterima dbHR] */
$PETA_ENUM = [

    // ---- gender ----
    // Form pegawai mengirim M / F, form keluarga mengirim MALE / FEMALE.
    // Ganti sisi kanan sesuai constraint asli di dbHR.
    // Kalau DB pakai 'Laki-laki'/'Perempuan', tulis itu di sisi kanan.
    'gender' => [
        'M'      => 'L',
        'F'      => 'P',
        'MALE'   => 'L',
        'FEMALE' => 'P',
    ],

    // ---- status_nikah ----
    // Form mengirim SINGLE / MARRIED / DIVORCED / WIDOWED
    'status_nikah' => [
        'SINGLE'   => 'Belum Menikah',
        'MARRIED'  => 'Menikah',
        'DIVORCED' => 'Cerai Hidup',
        'WIDOWED'  => 'Cerai Mati',
    ],

    // ---- agama ----
    // Form sudah mengirim Islam/Kristen/dst. Isi hanya kalau DB pakai ejaan lain.
    'agama' => [
        // 'Buddha' => 'Budha',
    ],

    // ---- status_karyawan ----
    // Nilai default yang di-set kode = 'Aktif'
    'status_karyawan' => [
        // 'Aktif' => 'AKTIF',
    ],
];

/**
 * Terjemahkan nilai form ke nilai dbHR.
 * Kalau tidak ada di peta, nilai dikirim apa adanya.
 */
function enum_db($kolom, $nilai) {
    global $PETA_ENUM;
    if ($nilai === null || $nilai === '') return null;
    return $PETA_ENUM[$kolom][$nilai] ?? $nilai;
}
