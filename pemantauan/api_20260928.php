<?php
// =====================================================================
// API PEMANTAUAN LOKASI (PC internal, port 8081)  →  /pemantauan/api.php?action=...
//
// GET  ping                              cek koneksi DB
// GET  init                              lokasi + checklist + statistik (1x panggil saat app dibuka)
// GET  locations                         daftar lokasi + status cek terakhir
// POST location_save      [admin]        {code,name,area,divisi,barcodeType}
// POST location_delete    [admin]        {code}   (soft delete)
// GET  checklist
// GET  reports            ?q=&status=&followup=&loc=&from=YYYY-MM-DD&to=YYYY-MM-DD&limit=200
// GET  report             ?id=
// POST report_save                       multipart: data(JSON) + photos[] (file)
// POST report_followup                   {id, followUp: open|proses|selesai, note}
// POST report_delete      [admin]        {id}
// GET  photo              ?id=           file gambar
// GET  export_csv         (filter sama dgn reports)
// GET  stats
// =====================================================================

require __DIR__ . '/lib.php';

header('Access-Control-Allow-Origin: ' . cfg('cors_origin'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Pin');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

set_exception_handler(function (Throwable $e) {
    error_log('[pemantauan] ' . $e->getMessage());
    fail(500, 'Kesalahan server: ' . $e->getMessage());
});

$action = $_GET['action'] ?? '';
$STATUS = ['aman', 'perhatian', 'bahaya'];
$FOLLOW = ['open', 'proses', 'selesai'];

switch ($action) {

case 'ping':
    ok(['db' => q("SELECT DB_NAME() AS db, SYSDATETIME() AS now")->fetch()]);

case 'init':
    ok(['locations' => locations(), 'checklist' => checklist(), 'stats' => stats()]);

case 'locations':
    ok(['locations' => locations()]);

case 'checklist':
    ok(['checklist' => checklist()]);

case 'stats':
    ok(['stats' => stats()]);

case 'location_save':
    require_method('POST'); require_admin();
    $b = body();
    $code = str($b['code'] ?? '', 50);
    $name = str($b['name'] ?? '', 150);
    if (!$code || !$name) fail(400, 'Kode dan nama lokasi wajib diisi.');
    $code = strtoupper($code);
    $type = strtoupper($b['barcodeType'] ?? 'QR') === 'CODE128' ? 'CODE128' : 'QR';
    $p = [':name' => $name, ':area' => str($b['area'] ?? '', 150), ':div' => str($b['divisi'] ?? '', 80), ':type' => $type, ':code' => $code];
    $exists = q("SELECT LocationId FROM dbo.MonLocation WHERE Code = :code", [':code' => $code])->fetchColumn();
    if ($exists) {
        q("UPDATE dbo.MonLocation SET Name=:name, Area=:area, Divisi=:div, BarcodeType=:type, IsActive=1, UpdatedAt=SYSDATETIME() WHERE Code=:code", $p);
    } else {
        q("INSERT INTO dbo.MonLocation (Name, Area, Divisi, BarcodeType, Code) VALUES (:name, :area, :div, :type, :code)", $p);
    }
    ok(['locations' => locations()]);

case 'location_delete':
    require_method('POST'); require_admin();
    $code = str(body()['code'] ?? '', 50);
    if (!$code) fail(400, 'Kode kosong.');
    q("UPDATE dbo.MonLocation SET IsActive = 0, UpdatedAt = SYSDATETIME() WHERE Code = :c", [':c' => $code]);
    ok(['locations' => locations()]);

case 'reports':
    [$where, $params] = report_filter();
    $limit = max(1, min(1000, (int)($_GET['limit'] ?? 200)));
    $rows = q("SELECT TOP ($limit) r.ReportId AS id, r.ReportNo AS reportNo, r.LocationCode AS locCode, r.LocationName AS locName,
                      r.Area AS area, r.Petugas AS petugas, r.Divisi AS divisi, r.Shift AS shift, r.Status AS status,
                      r.Catatan AS catatan, r.PIC AS pic, r.FollowUp AS followUp,
                      CONVERT(varchar(19), r.CreatedAt, 126) AS createdAt,
                      (SELECT COUNT(*) FROM dbo.MonReportPhoto p WHERE p.ReportId = r.ReportId) AS photoCount,
                      (SELECT TOP (1) p.PhotoId FROM dbo.MonReportPhoto p WHERE p.ReportId = r.ReportId ORDER BY p.SortOrder) AS firstPhotoId
               FROM dbo.MonReport r $where ORDER BY r.CreatedAt DESC", $params)->fetchAll();
    ok(['reports' => $rows]);

case 'report':
    $id = (int)($_GET['id'] ?? 0);
    $r = q("SELECT ReportId AS id, ReportNo AS reportNo, LocationCode AS locCode, LocationName AS locName, Area AS area,
                   Petugas AS petugas, Divisi AS divisi, Shift AS shift, Status AS status, Catatan AS catatan,
                   TindakLanjut AS tindakLanjut, PIC AS pic, FollowUp AS followUp, FollowUpNote AS followUpNote,
                   CONVERT(varchar(19), FollowUpAt, 126) AS followUpAt, CONVERT(varchar(19), CreatedAt, 126) AS createdAt
            FROM dbo.MonReport WHERE ReportId = :id", [':id' => $id])->fetch();
    if (!$r) fail(404, 'Laporan tidak ditemukan.');
    $r['checks'] = q("SELECT ItemKey AS [key], Label AS label, Result AS result FROM dbo.MonReportCheck WHERE ReportId = :id", [':id' => $id])->fetchAll();
    $r['photos'] = q("SELECT PhotoId AS id, Caption AS caption FROM dbo.MonReportPhoto WHERE ReportId = :id ORDER BY SortOrder", [':id' => $id])->fetchAll();
    ok(['report' => $r]);

case 'report_save':
    require_method('POST');
    $b = body();
    $code    = str($b['locCode'] ?? '', 50);
    $petugas = str($b['petugas'] ?? '', 100);
    $status  = $b['status'] ?? '';
    if (!$code)    fail(400, 'Kode lokasi kosong.');
    if (!$petugas) fail(400, 'Nama petugas wajib diisi.');
    if (!in_array($status, $STATUS, true)) fail(400, 'Status tidak valid.');

    $files = normalize_files($_FILES['photos'] ?? null);
    if (count($files) < 1) fail(400, 'Minimal 1 foto.');
    if (count($files) > cfg('max_photos')) fail(400, 'Maksimal ' . cfg('max_photos') . ' foto.');
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) fail(400, 'Upload foto gagal (kode ' . $f['error'] . '). Cek upload_max_filesize / post_max_size di php.ini.');
        if ($f['size'] > cfg('max_photo_bytes')) fail(400, 'Ukuran foto terlalu besar.');
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) fail(400, 'File bukan gambar JPG/PNG/WEBP.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $saved = [];
    try {
        // lokasi: pakai yang terdaftar, atau daftarkan baru dari lapangan
        $loc = q("SELECT LocationId, Code, Name, Area FROM dbo.MonLocation WHERE Code = :c", [':c' => $code])->fetch();
        if (!$loc) {
            $newName = str($b['newLocation']['name'] ?? '', 150);
            if (!$newName) throw new RuntimeException('Kode belum terdaftar, isi nama lokasi.');
            $lid = q("INSERT INTO dbo.MonLocation (Code, Name, Area, Divisi) OUTPUT INSERTED.LocationId VALUES (:c, :n, :a, :d)",
                     [':c' => strtoupper($code), ':n' => $newName, ':a' => str($b['newLocation']['area'] ?? '', 150), ':d' => str($b['divisi'] ?? '', 80)])->fetchColumn();
            $loc = ['LocationId' => (int)$lid, 'Code' => strtoupper($code), 'Name' => $newName, 'Area' => str($b['newLocation']['area'] ?? '', 150)];
        }

        // nomor laporan MON/yymm/00001 (aman dari bentrok via UPDLOCK)
        $ym = date('ym');
        $prefix = cfg('report_prefix') . "/$ym/";
        $next = (int)q("SELECT ISNULL(MAX(CAST(RIGHT(ReportNo, 5) AS INT)), 0) + 1 FROM dbo.MonReport WITH (UPDLOCK, HOLDLOCK) WHERE ReportNo LIKE :p",
                       [':p' => $prefix . '%'])->fetchColumn();
        $reportNo = $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);

        $rid = (int)q("INSERT INTO dbo.MonReport (ReportNo, LocationId, LocationCode, LocationName, Area, Petugas, Divisi, Shift, Status,
                                                  Catatan, TindakLanjut, PIC, ClientIP, UserAgent)
                       OUTPUT INSERTED.ReportId
                       VALUES (:no, :lid, :lc, :ln, :ar, :pt, :dv, :sh, :st, :ct, :tl, :pic, :ip, :ua)", [
            ':no' => $reportNo, ':lid' => (int)$loc['LocationId'], ':lc' => $loc['Code'], ':ln' => $loc['Name'], ':ar' => $loc['Area'],
            ':pt' => $petugas, ':dv' => str($b['divisi'] ?? '', 80), ':sh' => str($b['shift'] ?? '', 20), ':st' => $status,
            ':ct' => str($b['catatan'] ?? '', 4000), ':tl' => str($b['tindakLanjut'] ?? '', 500), ':pic' => str($b['pic'] ?? '', 150),
            ':ip' => mb_substr(client_ip(), 0, 45), ':ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
        ])->fetchColumn();

        // checklist
        $labels = array_column(checklist(), 'label', 'key');
        foreach (($b['checks'] ?? []) as $k => $v) {
            if (!isset($labels[$k]) || !in_array($v, ['ok', 'masalah'], true)) continue;
            q("INSERT INTO dbo.MonReportCheck (ReportId, ItemKey, Label, Result) VALUES (:r, :k, :l, :v)",
              [':r' => $rid, ':k' => $k, ':l' => $labels[$k], ':v' => $v]);
        }

        // foto → uploads/yyyy/mm/
        $sub = date('Y') . '/' . date('m');
        $dir = rtrim(cfg('upload_dir'), '/\\') . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Folder uploads tidak bisa dibuat. Cek permission.');
        $captions = $b['captions'] ?? [];
        foreach ($files as $i => $f) {
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][getimagesize($f['tmp_name'])[2]];
            $name = str_replace('/', '-', $reportNo) . '_' . ($i + 1) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new RuntimeException('Gagal menyimpan foto ke disk.');
            $saved[] = "$dir/$name";
            q("INSERT INTO dbo.MonReportPhoto (ReportId, FilePath, Caption, SizeBytes, SortOrder) VALUES (:r, :f, :c, :s, :o)",
              [':r' => $rid, ':f' => "$sub/$name", ':c' => str($captions[$i] ?? '', 300), ':s' => (int)$f['size'], ':o' => $i]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($saved as $s) @unlink($s);
        fail(400, $e->getMessage());
    }
    ok(['id' => $rid, 'reportNo' => $reportNo]);

case 'report_followup':
    require_method('POST');
    $b = body();
    $fu = $b['followUp'] ?? '';
    if (!in_array($fu, $FOLLOW, true)) fail(400, 'Status tindak lanjut tidak valid.');
    q("UPDATE dbo.MonReport SET FollowUp = :f, FollowUpNote = :n, FollowUpAt = SYSDATETIME() WHERE ReportId = :id",
      [':f' => $fu, ':n' => str($b['note'] ?? '', 500), ':id' => (int)($b['id'] ?? 0)]);
    ok();

case 'report_delete':
    require_method('POST'); require_admin();
    $id = (int)(body()['id'] ?? 0);
    $files = q("SELECT FilePath FROM dbo.MonReportPhoto WHERE ReportId = :id", [':id' => $id])->fetchAll(PDO::FETCH_COLUMN);
    q("DELETE FROM dbo.MonReport WHERE ReportId = :id", [':id' => $id]);   // cascade ke check & photo
    foreach ($files as $f) @unlink(rtrim(cfg('upload_dir'), '/\\') . '/' . $f);
    ok();

case 'photo':
    $p = q("SELECT FilePath FROM dbo.MonReportPhoto WHERE PhotoId = :id", [':id' => (int)($_GET['id'] ?? 0)])->fetchColumn();
    $base = realpath(cfg('upload_dir'));
    $path = $p ? realpath(cfg('upload_dir') . '/' . $p) : false;
    if (!$path || !$base || strpos($path, $base) !== 0) { http_response_code(404); exit; }
    header('Content-Type: ' . (mime_content_type($path) ?: 'image/jpeg'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=604800');
    readfile($path);
    exit;

case 'export_csv':
    [$where, $params] = report_filter();
    $rows = q("SELECT r.ReportId, r.ReportNo, r.CreatedAt, r.LocationCode, r.LocationName, r.Area, r.Petugas, r.Divisi, r.Shift,
                      r.Status, r.Catatan, r.TindakLanjut, r.PIC, r.FollowUp,
                      (SELECT COUNT(*) FROM dbo.MonReportPhoto p WHERE p.ReportId = r.ReportId) AS JumlahFoto
               FROM dbo.MonReport r $where ORDER BY r.CreatedAt DESC", $params)->fetchAll();
    $items = checklist();
    $checks = [];
    if ($rows) {
        $ids = implode(',', array_map('intval', array_column($rows, 'ReportId')));
        foreach (q("SELECT ReportId, ItemKey, Result FROM dbo.MonReportCheck WHERE ReportId IN ($ids)")->fetchAll() as $c)
            $checks[$c['ReportId']][$c['ItemKey']] = $c['Result'];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-pemantauan-' . date('Ymd-His') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBFsep=;\n");
    fputcsv($o, array_merge(['No Laporan', 'Tanggal', 'Jam', 'Kode Lokasi', 'Nama Lokasi', 'Area', 'Petugas', 'Divisi', 'Shift', 'Status'],
                            array_column($items, 'label'), ['Catatan', 'Tindak Lanjut', 'PIC', 'Follow Up', 'Jumlah Foto']), ';');
    foreach ($rows as $r) {
        $dt = $r['CreatedAt'] instanceof DateTimeInterface ? $r['CreatedAt'] : new DateTime((string)$r['CreatedAt']);
        $cs = array_map(fn($it) => ['ok' => 'OK', 'masalah' => 'MASALAH'][$checks[$r['ReportId']][$it['key']] ?? ''] ?? '-', $items);
        fputcsv($o, array_merge([$r['ReportNo'], $dt->format('d/m/Y'), $dt->format('H:i'), $r['LocationCode'], $r['LocationName'], $r['Area'],
                                 $r['Petugas'], $r['Divisi'], $r['Shift'], $r['Status']], $cs,
                                [str_replace(["\r", "\n"], ' ', (string)$r['Catatan']), $r['TindakLanjut'], $r['PIC'], $r['FollowUp'], $r['JumlahFoto']]), ';');
    }
    fclose($o);
    exit;

default:
    fail(404, 'Action tidak dikenal: ' . $action);
}

// ---------------------------------------------------------------------

function locations(): array {
    return q("SELECT Code AS code, Name AS name, Area AS area, Divisi AS divisi, BarcodeType AS barcodeType,
                     LastReportNo AS lastReportNo, CONVERT(varchar(19), LastCheckAt, 126) AS lastCheckAt,
                     LastStatus AS lastStatus, LastPetugas AS lastPetugas, CheckedToday AS checkedToday
              FROM dbo.vw_MonLocationStatus ORDER BY Code")->fetchAll();
}

function stats(): array {
    return q("SELECT
                (SELECT COUNT(*) FROM dbo.MonReport WHERE CAST(CreatedAt AS DATE) = CAST(SYSDATETIME() AS DATE)) AS reportsToday,
                (SELECT COUNT(*) FROM dbo.vw_MonLocationStatus WHERE CheckedToday = 1) AS locationsCheckedToday,
                (SELECT COUNT(*) FROM dbo.MonLocation WHERE IsActive = 1) AS locationsTotal,
                (SELECT COUNT(*) FROM dbo.MonReport WHERE Status = 'perhatian' AND FollowUp <> 'selesai') AS perhatianOpen,
                (SELECT COUNT(*) FROM dbo.MonReport WHERE Status = 'bahaya' AND FollowUp <> 'selesai') AS bahayaOpen")->fetch();
}

function report_filter(): array {
    $w = []; $p = [];
    if (!empty($_GET['q'])) {
        $like = '%' . mb_substr($_GET['q'], 0, 100) . '%';
        $w[] = "(r.LocationName LIKE :q1 OR r.LocationCode LIKE :q2 OR r.Petugas LIKE :q3 OR r.Catatan LIKE :q4 OR r.ReportNo LIKE :q5 OR r.PIC LIKE :q6)";
        foreach (range(1, 6) as $i) $p[":q$i"] = $like;
    }
    if (!empty($_GET['status']))   { $w[] = "r.Status = :st";       $p[':st'] = $_GET['status']; }
    if (!empty($_GET['followup'])) { $w[] = "r.FollowUp = :fu";     $p[':fu'] = $_GET['followup']; }
    if (!empty($_GET['loc']))      { $w[] = "r.LocationCode = :lc"; $p[':lc'] = $_GET['loc']; }
    if (!empty($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) { $w[] = "r.CreatedAt >= :df"; $p[':df'] = $_GET['from']; }
    if (!empty($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   { $w[] = "r.CreatedAt < DATEADD(day, 1, CAST(:dt AS DATE))"; $p[':dt'] = $_GET['to']; }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

function normalize_files($f): array {
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $n)
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    return $out;
}
