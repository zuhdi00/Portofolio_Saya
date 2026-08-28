<?php
$folder_path = 'Z:/';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query !== '') {
    $files = glob($folder_path . '*' . $query . '*.jpg');

    foreach ($files as $file) {
        $results[] = [
            'name' => basename($file),
            'path' => $file
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pencarian Gambar Master Design</title>
</head>
<body>
    <h2>Pencarian Gambar Master Design</h2>
    <form method="get">
        <input type="text" name="q" placeholder="Masukkan nama file..." value="<?= htmlspecialchars($query) ?>" required>
        <button type="submit">Cari</button>
    </form>

    <?php if ($query !== ''): ?>
        <h3>Hasil Pencarian: <?= htmlspecialchars($query) ?></h3>
        <?php if (empty($results)): ?>
            <p><strong>Tidak ada file ditemukan.</strong></p>
        <?php else: ?>
            <?php foreach ($results as $r): ?>
                <div style="margin-bottom:20px;">
                    <p><?= htmlspecialchars($r['name']) ?></p>
                    <img src="file:///<?= str_replace('\\', '/', $r['path']) ?>" width="300">
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
