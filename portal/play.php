<?php
require __DIR__ . '/config.php';
$slug = $_GET['slug'] ?? '';
$st = mpi_db()->prepare('SELECT * FROM mpi_games WHERE slug = ? AND aktif = 1');
$st->execute([$slug]);
$game = $st->fetch();
if (!$game) { http_response_code(404); exit('Game tidak ditemukan.'); }
$src = 'game/' . ltrim($game['file_path'], '/');
// Beri tahu game slug & id-nya agar bisa ambil soal dari API.
$sep = (strpos($src, '?') === false) ? '?' : '&';
$src .= $sep . 'game=' . urlencode($game['slug']) . '&id=' . (int) $game['id'];
// Anti-cache: tambah versi berdasarkan waktu modifikasi file game
$file = __DIR__ . '/game/' . ltrim($game['file_path'], '/');
if (is_file($file)) { $src .= '&v=' . filemtime($file); }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($game['nama']) ?> — MPI SMP 5 Tegal</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="playframe">
  <div class="bar">
    <a class="btn btn-secondary btn-sm" href="index.php">← Beranda</a>
    <strong><?= e($game['icon']) ?> <?= e($game['nama']) ?></strong>
    <span class="muted"><?= e($game['slug']) ?></span>
  </div>
  <iframe src="<?= e($src) ?>" allow="camera; microphone; fullscreen; autoplay"></iframe>
</div>
</body>
</html>
