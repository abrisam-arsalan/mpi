<?php
require __DIR__ . '/config.php';
if (mpi_user()) { mpi_redirect('admin.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mpi_csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = mpi_db()->prepare('SELECT * FROM mpi_users WHERE username = ?');
    $st->execute([$u]);
    $row = $st->fetch();
    if ($row && password_verify($p, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $row['id'];
        mpi_redirect('admin.php');
    }
    $error = 'Username atau password salah.';
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
