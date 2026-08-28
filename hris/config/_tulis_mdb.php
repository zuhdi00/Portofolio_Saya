<?php
/**
 * config/_tulis_mdb.php
 * Helper menulis pegawai baru ke USERINFO di MDB ZKTeco (mode testing lokal).
 * Struktur kolom disesuaikan dgn MDB asli PT Supracor.
 *
 * PENTING:
 *  - Hanya menambah NAMA ke daftar. TIDAK mendaftarkan biometrik.
 *    Karyawan tetap TIDAK bisa absen sampai wajah didaftarkan di mesin fisik.
 *  - Menulis ke MDB testing lokal. Insert cepat lalu tutup koneksi.
 *  - USERID baru mengikuti USERID terakhir; Badgenumber mengikuti badge terakhir.
 *  - Nilai default kolom mengikuti baris pegawai yang sudah ada,
 *    supaya software ZKTeco tidak error saat membaca.
 */

/**
 * Tambah pegawai ke USERINFO MDB.
 * @param string  $nama     nama pegawai
 * @param string  $badge    Dipertahankan untuk kompatibilitas, tidak digunakan sebagai ID MDB
 * @param int     $deptId   DEFAULTDEPTID (default 22)
 * @param string  $gender   'M' / 'F'
 * @param ?string $tglMasuk 'Y-m-d' -> dikonversi ke m/d/Y
 * @return array  ['ok'=>bool, 'userid'=>int|null, 'pesan'=>string]
 */
function tulisPegawaiKeMDB(string $nama, string $badge, int $deptId = 22,
                           string $gender = 'M', ?string $tglMasuk = null): array {
    $hasil = ['ok'=>false, 'userid'=>null, 'pesan'=>''];
    try {
        $mdb = buka_mdb_tulis();

        // 1. Ambil baris USERID terakhir, lalu lanjutkan badge dari baris yang sama.
        $lastUser = $mdb->query(
            "SELECT TOP 1 USERID, Badgenumber FROM USERINFO ORDER BY USERID DESC"
        )->fetch(PDO::FETCH_ASSOC);
        $useridBaru = $lastUser ? ((int)$lastUser['USERID'] + 1) : 1;
        $badgenumberBaru = 1;
        $lastBadge = $lastUser ? $lastUser['Badgenumber'] : null;
        if ($lastBadge !== false && ctype_digit(trim((string)$lastBadge))) {
            $badgenumberBaru = (int)trim((string)$lastBadge) + 1;
        }
        // Lewati badge yang sudah ada, tanpa memakai nomor anomali dari record lama.
        $badgeCheck = $mdb->prepare("SELECT COUNT(*) FROM USERINFO WHERE Badgenumber = ?");
        while ((int)$badgeCheck->execute([(string)$badgenumberBaru]) && (int)$badgeCheck->fetchColumn() > 0) {
            $badgenumberBaru++;
        }

        // 2. format tanggal masuk -> m/d/Y (format Access di MDB)
        $hired = null;
        if ($tglMasuk) {
            $t = date_create($tglMasuk);
            if ($t) $hired = $t->format('m/d/Y');
        }
        if (!$hired) $hired = date('m/d/Y');

        $g = strtoupper(substr($gender,0,1));
        if ($g !== 'F') $g = 'M';   // default M

        // 3. insert dgn nilai default mengikuti baris yang ada
        $sql = "INSERT INTO USERINFO
                (USERID, Badgenumber, Name, Gender, HIREDDAY, DEFAULTDEPTID,
                 VERIFICATIONMETHOD, SECURITYFLAGS, ATT, INLATE, OUTEARLY, OVERTIME,
                 SEP, HOLIDAY, privilege, InheritDeptSch, InheritDeptSchClass,
                 AutoSchPlan, MinAutoSchInterval, RegisterOT, InheritDeptRule,
                 EMPRIVILEGE, UseAccGroupTZ, Expires, ValidCount)
                VALUES (?, ?, ?, ?, ?, ?,
                        1, 1, 1, 0, 0, 1,
                        1, 1, 0, 1, 1,
                        1, 24, 1, 0,
                        0, 1, 0, 0)";
        $st = $mdb->prepare($sql);
        $ok = $st->execute([$useridBaru, (string)$badgenumberBaru, $nama, $g, $hired, $deptId]);

        if ($ok) {
            $hasil['ok'] = true;
            $hasil['userid'] = $useridBaru;
            $hasil['pesan'] = "Ditambahkan ke MDB (USERID $useridBaru, Badgenumber $badgenumberBaru).";
        } else {
            $hasil['pesan'] = "Insert gagal.";
        }
        $mdb = null;   // tutup segera
    } catch (Exception $e) {
        $hasil['pesan'] = "Error MDB: " . $e->getMessage();
    }
    return $hasil;
}

/** buka MDB mode TULIS (tanpa ReadOnly) */
function buka_mdb_tulis(): PDO {
    $path = 'C:\\zkteco_data\\ATT2000.mdb';
    if (!@file_exists($path)) {
        throw new Exception("MDB testing tidak ditemukan: $path");
    }
    $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq={$path};";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
