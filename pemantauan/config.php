<?php
// =====================================================================
// KONFIGURASI BACKEND – disesuaikan dengan koneksi SC List (spsdmz2)
// =====================================================================

return [
    // --- KONEKSI DATABASE (PDO sqlsrv) ---
    'db_server'     => 'spsdmz2',
    'db_name'       => 'SPS_Pemantauan',   // database hasil sql/01_schema.sql
                                           // atau 'dbSopanusa' jika tabel Mon* dibuat di sana
    'db_user'       => 'sa',
    'db_pass'       => 'supracor',
    'db_trust_cert' => true,               // TrustServerCertificate=1 (sesuai hasil tes diagnostik)
    'db_encrypt'    => null,               // null = ikut default driver (sama seperti SC List)

    // --- Foto ---
    'upload_dir'      => __DIR__ . '/uploads',
    'max_photos'      => 12,
    'max_photo_bytes' => 8 * 1024 * 1024,

    // --- Keamanan ---
    'admin_pin'   => 'supracorzuhdi',      // password halaman Kelola barcode & aksi admin
    'cors_origin' => '*',

    'timezone'      => 'Asia/Jakarta',
    'report_prefix' => 'MON',
];
