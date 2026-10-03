<?php
require __DIR__ . '/config.php';
try {
    $games = mpi_db()->query("SELECT g.*,
        (SELECT COUNT(*) FROM mpi_soal s WHERE s.game_id = g.id AND s.is_demo = 1) AS demo_count,
        (SELECT COUNT(*) FROM mpi_soal s WHERE s.game_id = g.id AND s.is_demo = 0) AS guru_count
        FROM mpi_games g WHERE g.aktif = 1 ORDER BY g.urutan, g.id")->fetchAll();
} catch (Throwable $ex) {
    $games = [];
    $err = $ex->getMessage();
}
$user = mpi_user();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MPI SMP 5 Tegal — Media Pembelajaran Interaktif</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">
    <span class="logo">🎓</span>
    <div>
      <h1>MPI SMP 5 Tegal</h1>
      <div class="sub">Media Pembelajaran Interaktif</div>
    </div>
  </div>
  <div class="right">
    <?php if ($user): ?>
      <span>👤 <?= e($user['nama']) ?></span>
      <a class="btn btn-primary btn-sm" href="admin.php">⚙️ Kelola</a>
      <a class="btn btn-secondary btn-sm" href="logout.php">Keluar</a>
    <?php else: ?>
      <a class="btn btn-primary btn-sm" href="login.php">Login Guru</a>
    <?php endif; ?>
  </div>
</header>

<main class="wrap">
  <?php if (!empty($err)): ?>
    <div class="alert err">Database belum siap: <?= e($err) ?></div>
  <?php endif; ?>

  <?php if (!$games): ?>
    <p class="empty">Belum ada game. Silakan <a href="login.php">login</a> lalu tambahkan game.</p>
  <?php else: ?>
    <div class="alert ok">Klik game, lalu pilih <b>kelas</b> dan <b>sumber soal</b> sebelum bermain. Tanpa akun guru? Pilih <b>Soal Demo</b>.</div>
    <div class="grid">
      <?php foreach ($games as $g): ?>
      <a class="card" href="play.php?slug=<?= e($g['slug']) ?>">
        <div class="icon"><?= e($g['icon']) ?></div>
        <div class="nama"><?= e($g['nama']) ?></div>
        <div class="desc"><?= e($g['deskripsi']) ?></div>
        <div><span class="pill"><?= (int) $g['demo_count'] ?> demo</span> <span class="pill"><?= (int) $g['guru_count'] ?> soal guru</span></div>
        <div class="play">▶ Mainkan</div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
