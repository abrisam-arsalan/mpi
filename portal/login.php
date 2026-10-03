<?php
require __DIR__ . '/config.php';

// Tujuan setelah login: halaman setup game (play.php?…) atau panel admin.
// Whitelist sederhana — hanya redirect relatif dalam portal, tolak // atau http.
$next = (string) ($_GET['next'] ?? '');
if (strncmp($next, 'play.php', 8) !== 0 && strncmp($next, 'admin.php', 9) !== 0) { $next = 'admin.php'; }

if (mpi_user()) { mpi_redirect($next); }

// ------------------------------------------------------------
// Pembatas percobaan login (anti brute-force) berbasis session.
// Panel ini terbuka ke internet, jadi tanpa pembatas siapa pun bisa
// mencoba password berulang kali tanpa hambatan. Tidak perlu tabel baru.
// ------------------------------------------------------------
$MAX_GAGAL   = 5;    // jumlah percobaan gagal sebelum dikunci
$KUNCI_DETIK = 60;   // lama kunci (detik)

$error = '';
$now = time();
if (!isset($_SESSION['login_gagal']))        { $_SESSION['login_gagal'] = 0; }
if (!isset($_SESSION['login_kunci_sampai'])) { $_SESSION['login_kunci_sampai'] = 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mpi_csrf_check();
    if ($_SESSION['login_kunci_sampai'] > $now) {
        $sisa = (int) ($_SESSION['login_kunci_sampai'] - $now);
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $sisa . ' detik.';
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $st = mpi_db()->prepare('SELECT * FROM mpi_users WHERE username = ?');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($p, $row['password_hash'])) {
            // Berhasil: bersihkan penghitung lalu amankan session.
            $_SESSION['login_gagal'] = 0;
            $_SESSION['login_kunci_sampai'] = 0;
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $row['id'];
            mpi_redirect($next);
        }
        $_SESSION['login_gagal']++;
        if ($_SESSION['login_gagal'] >= $MAX_GAGAL) {
            $_SESSION['login_gagal'] = 0;
            $_SESSION['login_kunci_sampai'] = $now + $KUNCI_DETIK;
            $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $KUNCI_DETIK . ' detik.';
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Guru — MPI SMP 5 Tegal</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-box">
  <h2>🔐 Login Guru</h2>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
    <div class="form-row">
      <label>Username</label>
      <input name="username" autocomplete="username" required autofocus>
    </div>
    <div class="form-row">
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn-primary" style="width:100%">Masuk</button>
  </form>
  <p class="muted" style="margin-top:16px"><a href="index.php">← Kembali ke beranda</a></p>
</div>
</body>
</html>
