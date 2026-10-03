<?php
require __DIR__ . '/config.php';
$slug = $_GET['slug'] ?? '';
$st = mpi_db()->prepare('SELECT * FROM mpi_games WHERE slug = ? AND aktif = 1');
$st->execute([$slug]);
$game = $st->fetch();
if (!$game) { http_response_code(404); exit('Game tidak ditemukan.'); }
$user = mpi_user();

// ============================================================
// MODE MAIN (?start=1) — pilihan guru sudah dipilih, langsung tayangkan game.
// ============================================================
if (($_GET['start'] ?? '') === '1') {
    $sumber = $_GET['sumber'] ?? 'demo';
    if (!in_array($sumber, ['demo', 'guru'], true)) { $sumber = 'demo'; }
    $kelas = $_GET['kelas'] ?? '';
    if (!in_array($kelas, ['7', '8', '9'], true)) { $kelas = ''; }
    $mapel = (int) ($_GET['mapel'] ?? 0);

    // Soal guru hanya untuk yang sudah login. Setelah login, guru kembali
    // ke URL ini (login.php?next=…) dan game langsung tayang.
    if ($sumber === 'guru' && !$user) {
        $next = 'play.php?' . http_build_query(['slug' => $slug, 'start' => 1, 'sumber' => 'guru', 'kelas' => $kelas, 'mapel' => $mapel]);
        mpi_redirect('login.php?next=' . urlencode($next));
    }

    // Pilihan ini dibaca api/questions.php lewat session — file game
    // tidak perlu tahu-menahu soal kelas/mapel/sumber.
    $_SESSION['mpi_setup'][(int) $game['id']] = ['sumber' => $sumber, 'kelas' => $kelas, 'mapel' => $mapel];

    $src = 'game/' . ltrim($game['file_path'], '/');
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
    <a class="btn btn-secondary btn-sm" href="play.php?slug=<?= e($game['slug']) ?>">← Ganti Soal</a>
    <strong><?= e($game['icon']) ?> <?= e($game['nama']) ?></strong>
    <span class="badge <?= $sumber === 'demo' ? 'badge-yellow' : '' ?>"><?= $sumber === 'demo' ? '🎲 Soal Demo' : '📚 Soal Guru' ?></span>
    <?php if ($kelas !== ''): ?><span class="badge">Kelas <?= e($kelas) ?></span><?php endif; ?>
  </div>
  <iframe src="<?= e($src) ?>" allow="camera; microphone; fullscreen; autoplay"></iframe>
</div>
</body>
</html>
    <?php
    exit;
}

// ============================================================
// MODE SETUP (default) — pilih sumber soal (demo / guru) + kelas + mapel
// SEBELUM game dimulai, supaya tidak ada permainan tanpa soal.
// ============================================================
$st = mpi_db()->prepare('SELECT SUM(is_demo = 0) guru, SUM(is_demo = 1) demo FROM mpi_soal WHERE game_id = ?');
$st->execute([(int) $game['id']]);
$jumlah = $st->fetch() ?: ['guru' => 0, 'demo' => 0];
$mapelList = mpi_db()->query('SELECT * FROM mpi_mapel ORDER BY nama')->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($game['nama']) ?> — Siap Main — MPI SMP 5 Tegal</title>
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
      <a class="btn btn-secondary btn-sm" href="index.php">Beranda</a>
    <?php else: ?>
      <a class="btn btn-secondary btn-sm" href="index.php">Beranda</a>
      <a class="btn btn-primary btn-sm" href="login.php">Login Guru</a>
    <?php endif; ?>
  </div>
</header>

<main class="wrap">
  <div class="setup-card">
    <div class="setup-head">
      <div class="setup-icon"><?= e($game['icon']) ?></div>
      <div>
        <h1><?= e($game['nama']) ?></h1>
        <p class="muted"><?= e($game['deskripsi']) ?></p>
      </div>
    </div>

    <div class="setup-counts">
      <div class="count guru"><b id="cntGuru"><?= (int) $jumlah['guru'] ?></b><span>Soal Guru</span></div>
      <div class="count demo"><b id="cntDemo"><?= (int) $jumlah['demo'] ?></b><span>Soal Demo</span></div>
    </div>

    <form method="get">
      <input type="hidden" name="slug" value="<?= e($game['slug']) ?>">
      <input type="hidden" name="start" value="1">
      <div class="inline">
        <div class="form-row"><label>Kelas (pemisah materi)</label>
          <select name="kelas" id="kelasSel">
            <option value="">Semua kelas</option>
            <?php foreach (['7', '8', '9'] as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>Mata pelajaran (opsional)</label>
          <select name="mapel" id="mapelSel">
            <option value="0">Semua mapel</option>
            <?php foreach ($mapelList as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="setup-actions">
        <button class="btn btn-primary" name="sumber" value="demo" id="btnDemo">🎲 Mulai — Soal Demo</button>
        <button class="btn" name="sumber" value="guru" id="btnGuru">📚 Mulai — Soal Guru</button>
      </div>
    </form>
    <p class="setup-note">
      Tanpa akun? Pilih <b>Soal Demo</b> untuk mencoba game langsung.
      <b>Soal Guru</b> memakai Bank Soal milik sekolah (kelas 7/8/9)
      <?= $user ? '— Anda sudah login.' : '— login guru akan diminta sebelum game tayang.' ?>
    </p>
  </div>
</main>
<script>
// Hitung jumlah soal sesuai pilihan kelas/mapel lewat API publik (tanpa memuat ulang halaman).
(function () {
  var kelasSel = document.getElementById('kelasSel'), mapelSel = document.getElementById('mapelSel');
  var cntGuru = document.getElementById('cntGuru'), cntDemo = document.getElementById('cntDemo');
  var btnGuru = document.getElementById('btnGuru'), btnDemo = document.getElementById('btnDemo');
  function hitung() {
    var q = new URLSearchParams({ game: <?= json_encode($game['slug']) ?>, kelas: kelasSel.value, mapel: mapelSel.value });
    fetch('/api/questions.php?' + q, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var all = d.soal || [], guru = 0, demo = 0;
        all.forEach(function (s) { if (s.is_demo) { demo++; } else { guru++; } });
        cntGuru.textContent = guru; cntDemo.textContent = demo;
        btnGuru.disabled = guru === 0; btnDemo.disabled = demo === 0;
      })
      .catch(function () {});
  }
  kelasSel.addEventListener('change', hitung);
  mapelSel.addEventListener('change', hitung);
})();
</script>
</body>
</html>
