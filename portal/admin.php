<?php
require __DIR__ . '/config.php';
mpi_require_login();
$user = mpi_user();
$db = mpi_db();

// ============================================================
// Helper untuk AI (Gemini)
// ============================================================
function mpi_gemini(string $prompt): string {
    if (!MPI_GEMINI_KEY) {
        throw new Exception('API key Gemini belum diisi di secrets.php (MPI_GEMINI_KEY).');
    }
    $model = MPI_GEMINI_MODEL ?: 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model)
         . ':generateContent?key=' . urlencode(MPI_GEMINI_KEY);
    $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { throw new Exception('cURL error: ' . $err); }
    $data = json_decode((string) $res, true);
    if (isset($data['error'])) {
        throw new Exception('Gemini: ' . ($data['error']['message'] ?? 'error tidak diketahui'));
    }
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') { throw new Exception('Gemini mengembalikan teks kosong.'); }
    return $text;
}
function mpi_extract_json_array(string $text): array {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $start = strpos($text, '[');
    $end = strrpos($text, ']');
    if ($start === false || $end === false || $end <= $start) {
        throw new Exception('Jawaban AI bukan JSON array.');
    }
    $arr = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($arr)) { throw new Exception('JSON tidak valid: ' . json_last_error_msg()); }
    return $arr;
}
function mpi_parse_csv_soal(string $text): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    if (!$lines) { throw new Exception('File kosong.'); }
    $delim = (substr_count($lines[0], ';') >= 3) ? ';' : ',';
    $rows = [];
    foreach ($lines as $l) { $rows[] = str_getcsv($l, $delim); }
    if ($rows && preg_match('/soal/i', (string) ($rows[0][0] ?? ''))) { array_shift($rows); }
    $out = [];
    foreach ($rows as $r) {
        $q = trim($r[0] ?? '');
        if ($q === '') { continue; }
        $A = trim($r[1] ?? '') ?: 'Benar';
        $B = trim($r[2] ?? '') ?: 'Salah';
        $k = strtoupper(trim($r[3] ?? ''));
        if ($k !== 'A' && $k !== 'B') {
            if ($k === strtoupper($A)) { $k = 'A'; }
            elseif ($k === strtoupper($B)) { $k = 'B'; }
            else { throw new Exception('Kunci tidak dikenali pada soal: "' . mb_substr($q, 0, 40) . '"'); }
        }
        $out[] = ['pertanyaan' => $q, 'opsi_a' => $A, 'opsi_b' => $B, 'kunci' => $k, 'penjelasan' => trim($r[4] ?? '')];
    }
    if (!$out) { throw new Exception('Tidak ada soal terbaca dari file.'); }
    return $out;
}
function mpi_parse_famili100(string $text): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    $out = [];
    foreach ($lines as $l) {
        $parts = array_map('trim', explode(';', $l, 2));
        if ($parts[0] === '') { continue; }
        $out[] = ['teks' => $parts[0], 'poin' => (int) ($parts[1] ?? 0)];
    }
    if (!$out) { throw new Exception('Jawaban Famili 100 wajib diisi (format: teks;poin per baris).'); }
    return $out;
}

// ============================================================
// Proses aksi (POST)
// ============================================================
$tab = $_GET['tab'] ?? 'game';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mpi_csrf_check();
    $action = $_POST['action'] ?? '';
    $redirect = $_POST['redirect'] ?? ('admin.php?tab=' . urlencode($tab));
    try {
        if ($action === 'game_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [
                'slug'      => trim($_POST['slug'] ?? ''),
                'nama'      => trim($_POST['nama'] ?? ''),
                'deskripsi' => trim($_POST['deskripsi'] ?? ''),
                'icon'      => trim($_POST['icon'] ?? '🎮'),
                'file_path' => trim($_POST['file_path'] ?? ''),
                'tipe'      => trim($_POST['tipe'] ?? 'benar_salah'),
                'urutan'    => (int) ($_POST['urutan'] ?? 0),
                'aktif'     => isset($_POST['aktif']) ? 1 : 0,
            ];
            if ($data['slug'] === '' || $data['nama'] === '') { throw new Exception('Slug dan nama wajib diisi.'); }
            if ($id > 0) {
                $st = $db->prepare('UPDATE mpi_games SET slug=?,nama=?,deskripsi=?,icon=?,file_path=?,tipe=?,urutan=?,aktif=? WHERE id=?');
                $st->execute([$data['slug'],$data['nama'],$data['deskripsi'],$data['icon'],$data['file_path'],$data['tipe'],$data['urutan'],$data['aktif'],$id]);
                mpi_flash('Game diperbarui.');
            } else {
                $st = $db->prepare('INSERT INTO mpi_games (slug,nama,deskripsi,icon,file_path,tipe,urutan,aktif,guru_id) VALUES (?,?,?,?,?,?,?,?,?)');
                $st->execute([$data['slug'],$data['nama'],$data['deskripsi'],$data['icon'],$data['file_path'],$data['tipe'],$data['urutan'],$data['aktif'],$user['id']]);
                mpi_flash('Game ditambahkan.');
            }
        } elseif ($action === 'game_delete') {
            $st = $db->prepare('DELETE FROM mpi_games WHERE id = ?');
            $st->execute([(int) $_POST['id']]);
            mpi_flash('Game dihapus.');
        } elseif ($action === 'soal_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $game_id = (int) $_POST['game_id'];
            $st = $db->prepare('SELECT tipe FROM mpi_games WHERE id = ?');
            $st->execute([$game_id]);
            $gt = $st->fetchColumn() ?: 'benar_salah';

            $data = [
                'game_id'    => $game_id,
                'pertanyaan' => trim($_POST['pertanyaan'] ?? ''),
                'opsi_a'     => trim($_POST['opsi_a'] ?? ''),
                'opsi_b'     => trim($_POST['opsi_b'] ?? ''),
                'opsi_c'     => trim($_POST['opsi_c'] ?? ''),
                'opsi_d'     => trim($_POST['opsi_d'] ?? ''),
                'kunci'      => strtoupper($_POST['kunci'] ?? 'A'),
                'penjelasan' => trim($_POST['penjelasan'] ?? ''),
                'payload'    => null,
            ];
            if ($data['pertanyaan'] === '') { throw new Exception('Pertanyaan wajib diisi.'); }

            if ($gt === 'famili100') {
                $jawaban = mpi_parse_famili100($_POST['famili_jawaban'] ?? '');
                $data['payload'] = json_encode(['jawaban' => $jawaban], JSON_UNESCAPED_UNICODE);
                $data['opsi_a'] = 'Benar'; $data['opsi_b'] = 'Salah'; $data['kunci'] = 'A';
            } elseif ($gt === 'gesture') {
                $kategori = trim($_POST['gest_kategori'] ?? '');
                $data['payload'] = json_encode(['kategori' => $kategori], JSON_UNESCAPED_UNICODE);
                $data['opsi_a'] = 'Benar'; $data['opsi_b'] = 'Salah'; $data['kunci'] = 'A';
            } else {
                if (!in_array($data['kunci'], ['A', 'B', 'C', 'D'], true)) { throw new Exception('Kunci harus A, B, C, atau D.'); }
                if (in_array($gt, ['millionaire', 'clash'], true)) {
                    if ($data['opsi_a'] === '' || $data['opsi_b'] === '' || $data['opsi_c'] === '' || $data['opsi_d'] === '') {
                        throw new Exception('Isi keempat opsi A, B, C, dan D.');
                    }
                } else {
                    $data['opsi_a'] = $data['opsi_a'] !== '' ? $data['opsi_a'] : 'Benar';
                    $data['opsi_b'] = $data['opsi_b'] !== '' ? $data['opsi_b'] : 'Salah';
                }
            }

            if ($id > 0) {
                $st = $db->prepare('UPDATE mpi_soal SET game_id=?,pertanyaan=?,opsi_a=?,opsi_b=?,opsi_c=?,opsi_d=?,kunci=?,penjelasan=?,payload=? WHERE id=?');
                $st->execute([$data['game_id'],$data['pertanyaan'],$data['opsi_a'],$data['opsi_b'],$data['opsi_c'],$data['opsi_d'],$data['kunci'],$data['penjelasan'],$data['payload'],$id]);
                mpi_flash('Soal diperbarui.');
            } else {
                $st = $db->prepare('INSERT INTO mpi_soal (game_id,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,kunci,penjelasan,payload,guru_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$data['game_id'],$data['pertanyaan'],$data['opsi_a'],$data['opsi_b'],$data['opsi_c'],$data['opsi_d'],$data['kunci'],$data['penjelasan'],$data['payload'],$user['id']]);
                mpi_flash('Soal ditambahkan.');
            }
        } elseif ($action === 'soal_delete') {
            $st = $db->prepare('DELETE FROM mpi_soal WHERE id = ?');
            $st->execute([(int) $_POST['id']]);
            mpi_flash('Soal dihapus.');
        } elseif ($action === 'soal_upload') {
            $game_id = (int) $_POST['game_id'];
            if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Gagal mengunggah file CSV.');
            }
            $items = mpi_parse_csv_soal((string) file_get_contents($_FILES['csv']['tmp_name']));
            $st = $db->prepare('INSERT INTO mpi_soal (game_id,pertanyaan,opsi_a,opsi_b,kunci,penjelasan,guru_id) VALUES (?,?,?,?,?,?,?)');
            foreach ($items as $it) {
                $st->execute([$game_id,$it['pertanyaan'],$it['opsi_a'],$it['opsi_b'],$it['kunci'],$it['penjelasan'],$user['id']]);
            }
            mpi_flash(count($items) . ' soal berhasil diimpor.');
        } elseif ($action === 'ai_generate') {
            $game_id = (int) $_POST['game_id'];
            $topik = trim($_POST['topik'] ?? '');
            $jumlah = min(30, max(1, (int) ($_POST['jumlah'] ?? 5)));
            if ($topik === '') { throw new Exception('Topik wajib diisi.'); }
            $st = $db->prepare('SELECT * FROM mpi_games WHERE id = ?');
            $st->execute([$game_id]);
            $g = $st->fetch();
            if (!$g) { throw new Exception('Game tidak ditemukan.'); }
            if ($g['tipe'] === 'famili100') { throw new Exception('AI untuk Famili 100 belum didukung — isi manual lewat editor Bank Soal.'); }
            $tipe = $g['tipe'];
            $isPG = in_array($tipe, ['millionaire', 'clash'], true);
            if ($tipe === 'gesture') {
                $prompt = "Buatkan {$jumlah} kata/istilah untuk game tebak gerakan (charades) dengan topik: \"{$topik}\". "
                    . "Jawab HANYA dengan JSON array, tanpa teks lain. Setiap elemen objek dengan kunci: "
                    . '{"kata": string, "kategori": string}. '
                    . 'Contoh: [{"kata":"Dekomposisi","kategori":"Berpikir Komputasional"}]';
            } elseif ($isPG) {
                $prompt = "Buatkan {$jumlah} soal pilihan ganda 4 opsi (A, B, C, D) untuk game \"{$g['nama']}\" dengan topik: \"{$topik}\". "
                    . "Urutkan dari yang termudah ke tersulit. Jawab HANYA dengan JSON array, tanpa teks lain. Setiap elemen objek dengan kunci: "
                    . '{"pertanyaan": string, "opsi_a": string, "opsi_b": string, "opsi_c": string, "opsi_d": string, "kunci": "A"/"B"/"C"/"D", "penjelasan": string singkat}. '
                    . 'Contoh: [{"pertanyaan":"...","opsi_a":"...","opsi_b":"...","opsi_c":"...","opsi_d":"...","kunci":"C","penjelasan":"..."}]';
            } else {
                $prompt = "Buatkan {$jumlah} soal pilihan A/B (misal Benar/Salah) untuk game \"{$g['nama']}\" dengan topik: \"{$topik}\".\n"
                    . "Jawab HANYA dengan JSON array, tanpa teks lain. Setiap elemen objek dengan kunci: "
                    . '{"pertanyaan": string, "opsi_a": string, "opsi_b": string, "kunci": "A" atau "B", "penjelasan": string singkat}. '
                    . 'Contoh: [{"pertanyaan":"...","opsi_a":"Benar","opsi_b":"Salah","kunci":"B","penjelasan":"..."}]';
            }
            $text = mpi_gemini($prompt);
            $arr = mpi_extract_json_array($text);
            $clean = [];
            foreach ($arr as $it) {
                if ($tipe === 'gesture') {
                    $kata = trim($it['kata'] ?? $it['pertanyaan'] ?? '');
                    if ($kata === '') { continue; }
                    $clean[] = [
                        'pertanyaan' => $kata,
                        'opsi_a'     => 'Benar',
                        'opsi_b'     => 'Salah',
                        'opsi_c'     => '',
                        'opsi_d'     => '',
                        'kunci'      => 'A',
                        'penjelasan' => '',
                        'payload'    => json_encode(['kategori' => trim($it['kategori'] ?? '')], JSON_UNESCAPED_UNICODE),
                    ];
                } else {
                    if (empty($it['pertanyaan'])) { continue; }
                    $k = strtoupper(trim($it['kunci'] ?? ''));
                    if (!in_array($k, ['A', 'B', 'C', 'D'], true)) { $k = 'A'; }
                    $clean[] = [
                        'pertanyaan' => trim($it['pertanyaan']),
                        'opsi_a'     => trim($it['opsi_a'] ?? 'Benar') ?: 'Benar',
                        'opsi_b'     => trim($it['opsi_b'] ?? 'Salah') ?: 'Salah',
                        'opsi_c'     => trim($it['opsi_c'] ?? ''),
                        'opsi_d'     => trim($it['opsi_d'] ?? ''),
                        'kunci'      => $k,
                        'penjelasan' => trim($it['penjelasan'] ?? ''),
                        'payload'    => null,
                    ];
                }
            }
            if (!$clean) { throw new Exception('Tidak ada soal yang berhasil digenerate.'); }
            $_SESSION['ai_result'] = $clean;
            $_SESSION['ai_game_id'] = $game_id;
            mpi_flash('AI menghasilkan ' . count($clean) . ' soal. Tinjau lalu simpan.');
            $redirect = 'admin.php?tab=ai';
        } elseif ($action === 'ai_save') {
            $game_id = (int) ($_POST['game_id'] ?? 0);
            $items = $_SESSION['ai_result'] ?? [];
            $sel = array_map('intval', (array) ($_POST['save'] ?? []));
            if (!$items || !$sel) { throw new Exception('Tidak ada soal yang dipilih.'); }
            $st = $db->prepare('INSERT INTO mpi_soal (game_id,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,kunci,penjelasan,payload,guru_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $n = 0;
            foreach ($sel as $i) {
                if (!isset($items[$i])) { continue; }
                $it = $items[$i];
                $st->execute([$game_id,$it['pertanyaan'],$it['opsi_a'],$it['opsi_b'],$it['opsi_c'] ?? '',$it['opsi_d'] ?? '',$it['kunci'],$it['penjelasan'],$it['payload'] ?? null,$user['id']]);
                $n++;
            }
            unset($_SESSION['ai_result'], $_SESSION['ai_game_id']);
            mpi_flash($n . ' soal dari AI disimpan ke bank soal.');
            $redirect = 'admin.php?tab=soal';
        }
    } catch (Throwable $ex) {
        mpi_flash('Error: ' . $ex->getMessage(), 'err');
    }
    mpi_redirect($redirect);
}

// ============================================================
// Data untuk tampilan
// ============================================================
$games = $db->query('SELECT * FROM mpi_games ORDER BY urutan, id')->fetchAll();
$editGame = null;
if ($tab === 'game' && isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM mpi_games WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $editGame = $st->fetch();
}
$editSoal = null;
$editJawaban = '';
$editKategori = '';
if ($tab === 'soal' && isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM mpi_soal WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $editSoal = $st->fetch();
    if ($editSoal && !empty($editSoal['payload'])) {
        $p = json_decode($editSoal['payload'], true);
        if (is_array($p)) {
            if (!empty($p['kategori'])) { $editKategori = $p['kategori']; }
            if (!empty($p['jawaban'])) {
                $lines = [];
                foreach ($p['jawaban'] as $j) {
                    $lines[] = ($j['teks'] ?? '') . ';' . ($j['poin'] ?? 0);
                }
                $editJawaban = implode("\n", $lines);
            }
        }
    }
}
$soalFilter = (int) ($_GET['game'] ?? 0);
if ($soalFilter === 0 && $games) { $soalFilter = (int) $games[0]['id']; }
$soalList = [];
if ($soalFilter > 0) {
    $st = $db->prepare('SELECT * FROM mpi_soal WHERE game_id = ? ORDER BY id DESC LIMIT 200');
    $st->execute([$soalFilter]);
    $soalList = $st->fetchAll();
}
$totalSoal = (int) $db->query('SELECT COUNT(*) c FROM mpi_soal')->fetch()['c'];
$flash = mpi_take_flash();
$aiItems = $_SESSION['ai_result'] ?? null;
$aiGameId = (int) ($_SESSION['ai_game_id'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kelola MPI — SMP 5 Tegal</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">
    <span class="logo">🎓</span>
    <div><h1>Kelola MPI</h1><div class="sub"><?= e($user['nama']) ?> · <?= e($user['role']) ?></div></div>
  </div>
  <div class="right">
    <a class="btn btn-secondary btn-sm" href="index.php">🏠 Beranda</a>
    <a class="btn btn-secondary btn-sm" href="logout.php">Keluar</a>
  </div>
</header>

<main class="wrap">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['type'] === 'err' ? 'err' : 'ok' ?>"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="tabs">
    <a href="?tab=game" class="<?= $tab === 'game' ? 'active' : '' ?>">🎮 Game (<?= count($games) ?>)</a>
    <a href="?tab=soal" class="<?= $tab === 'soal' ? 'active' : '' ?>">📚 Bank Soal (<?= $totalSoal ?>)</a>
    <a href="?tab=ai" class="<?= $tab === 'ai' ? 'active' : '' ?>">🤖 Buat Soal (AI)</a>
  </div>

  <!-- ================= TAB GAME ================= -->
  <?php if ($tab === 'game'): ?>
    <div class="panel">
      <h3><?= $editGame ? '✏️ Edit Game' : '➕ Tambah Game' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
        <input type="hidden" name="action" value="game_save">
        <input type="hidden" name="id" value="<?= (int) ($editGame['id'] ?? 0) ?>">
        <div class="inline">
          <div class="form-row" style="flex:1;min-width:180px"><label>Nama game</label><input name="nama" required value="<?= e($editGame['nama'] ?? '') ?>"></div>
          <div class="form-row" style="flex:1;min-width:160px"><label>Slug (kata-kunci unik)</label><input name="slug" required value="<?= e($editGame['slug'] ?? '') ?>" placeholder="kamera-benar-salah"></div>
          <div class="form-row" style="width:110px"><label>Ikon</label><input name="icon" value="<?= e($editGame['icon'] ?? '🎮') ?>"></div>
        </div>
        <div class="form-row"><label>Deskripsi</label><input name="deskripsi" value="<?= e($editGame['deskripsi'] ?? '') ?>"></div>
        <div class="inline">
          <div class="form-row" style="flex:1"><label>File game (relatif ke /game/)</label><input name="file_path" value="<?= e($editGame['file_path'] ?? '') ?>" placeholder="kamera-benar-salah.html"></div>
          <div class="form-row" style="flex:1"><label>Tipe</label>
            <select name="tipe">
              <?php foreach (['benar_salah','famili100','clash','gesture','millionaire'] as $t): ?>
                <option value="<?= $t ?>" <?= ($editGame['tipe'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row" style="width:110px"><label>Urutan</label><input type="number" name="urutan" value="<?= (int) ($editGame['urutan'] ?? 0) ?>"></div>
        </div>
        <div class="inline" style="margin-top:6px">
          <label style="color:var(--muted);font-size:14px"><input type="checkbox" name="aktif" <?= !isset($editGame) || ($editGame['aktif'] ?? 1) ? 'checked' : '' ?>> Tampilkan di beranda</label>
          <button class="btn-primary"><?= $editGame ? 'Simpan Perubahan' : 'Tambah Game' ?></button>
          <?php if ($editGame): ?><a class="btn btn-secondary" href="?tab=game">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>Daftar Game</h3>
      <table>
        <tr><th>Ikon</th><th>Nama</th><th>Slug</th><th>Tipe</th><th>Urutan</th><th>File</th><th>Aktif</th><th>Aksi</th></tr>
        <?php foreach ($games as $g): ?>
        <tr>
          <td style="font-size:22px"><?= e($g['icon']) ?></td>
          <td><?= e($g['nama']) ?></td>
          <td><span class="badge"><?= e($g['slug']) ?></span></td>
          <td><?= e($g['tipe']) ?></td>
          <td><?= (int) $g['urutan'] ?></td>
          <td class="muted"><?= e($g['file_path']) ?></td>
          <td><?= $g['aktif'] ? '✅' : '—' ?></td>
          <td class="inline">
            <a class="btn btn-secondary btn-sm" href="?tab=game&edit=<?= (int) $g['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Hapus game ini beserta semua soalnya?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
              <input type="hidden" name="action" value="game_delete">
              <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
              <button class="btn-danger btn-sm">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

  <!-- ================= TAB SOAL ================= -->
  <?php if ($tab === 'soal'): ?>
    <div class="panel">
      <h3><?= $editSoal ? '✏️ Edit Soal' : '➕ Tambah Soal' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
        <input type="hidden" name="action" value="soal_save">
        <input type="hidden" name="id" value="<?= (int) ($editSoal['id'] ?? 0) ?>">
        <div class="form-row"><label>Game</label>
          <select name="game_id" id="gameSelect">
            <?php foreach ($games as $g): ?>
              <option value="<?= (int) $g['id'] ?>" data-tipe="<?= e($g['tipe']) ?>" <?= ($editSoal['game_id'] ?? $soalFilter) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['icon'] . ' ' . $g['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row"><label>Pertanyaan / perintah</label><textarea name="pertanyaan" required><?= e($editSoal['pertanyaan'] ?? '') ?></textarea></div>

        <div class="soal-group" data-show="benar_salah">
          <div class="inline">
            <div class="form-row" style="flex:1"><label>Opsi A</label><input name="opsi_a" value="<?= e($editSoal['opsi_a'] ?? 'Benar') ?>"></div>
            <div class="form-row" style="flex:1"><label>Opsi B</label><input name="opsi_b" value="<?= e($editSoal['opsi_b'] ?? 'Salah') ?>"></div>
          </div>
          <div class="form-row" style="width:160px"><label>Kunci</label>
            <select name="kunci">
              <option value="A" <?= ($editSoal['kunci'] ?? 'A') === 'A' ? 'selected' : '' ?>>A</option>
              <option value="B" <?= ($editSoal['kunci'] ?? 'A') === 'B' ? 'selected' : '' ?>>B</option>
            </select>
          </div>
        </div>

        <div class="soal-group" data-show="millionaire,clash">
          <div class="inline">
            <div class="form-row" style="flex:1"><label>Opsi A</label><input name="opsi_a" value="<?= e($editSoal['opsi_a'] ?? '') ?>"></div>
            <div class="form-row" style="flex:1"><label>Opsi B</label><input name="opsi_b" value="<?= e($editSoal['opsi_b'] ?? '') ?>"></div>
          </div>
          <div class="inline">
            <div class="form-row" style="flex:1"><label>Opsi C</label><input name="opsi_c" value="<?= e($editSoal['opsi_c'] ?? '') ?>"></div>
            <div class="form-row" style="flex:1"><label>Opsi D</label><input name="opsi_d" value="<?= e($editSoal['opsi_d'] ?? '') ?>"></div>
          </div>
          <div class="form-row" style="width:160px"><label>Kunci</label>
            <select name="kunci">
              <?php foreach (['A', 'B', 'C', 'D'] as $k): ?>
                <option value="<?= $k ?>" <?= ($editSoal['kunci'] ?? 'A') === $k ? 'selected' : '' ?>><?= $k ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="soal-group" data-show="famili100">
          <div class="form-row"><label>Jawaban &amp; poin (satu baris: teks;poin)</label>
            <textarea name="famili_jawaban" rows="6" placeholder="Dekomposisi;45&#10;Pengenalan Pola;25&#10;Algoritma;18&#10;Abstraksi;12"><?= e($editJawaban ?? '') ?></textarea>
          </div>
          <p class="muted">Poin biasanya menurun dari jawaban terpopuler. Total poin tidak harus 100.</p>
        </div>

        <div class="soal-group" data-show="gesture">
          <div class="form-row"><label>Kategori (misal: Hewan, Aktivitas, Berpikir Komputasional)</label>
            <input name="gest_kategori" value="<?= e($editKategori ?? '') ?>" placeholder="Kategori">
          </div>
          <p class="muted">Kata/istilah yang harus ditebak diisi pada kolom "Pertanyaan / perintah" di atas.</p>
        </div>

        <div class="form-row"><label>Penjelasan / catatan (opsional)</label><input name="penjelasan" value="<?= e($editSoal['penjelasan'] ?? '') ?>"></div>
        <div class="inline">
          <button class="btn-primary"><?= $editSoal ? 'Simpan Perubahan' : 'Tambah Soal' ?></button>
          <?php if ($editSoal): ?><a class="btn btn-secondary" href="?tab=soal">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>Impor Soal dari CSV</h3>
      <p class="muted">Kolom: <code>soal;opsiA;opsiB;kunci;penjelasan</code> (kunci = A/B). Unduh template di beranda game.</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
        <input type="hidden" name="action" value="soal_upload">
        <div class="inline">
          <div class="form-row" style="flex:1">
            <select name="game_id">
              <?php foreach ($games as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $soalFilter === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row" style="flex:1"><input type="file" name="csv" accept=".csv,.txt" required></div>
          <button class="btn-secondary">⬆ Impor CSV</button>
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>Daftar Soal
        <span class="muted">(filter: <a href="?tab=soal"><?= count($soalList) ?></a>)</span>
      </h3>
      <form method="get" class="inline" style="margin-bottom:12px">
        <input type="hidden" name="tab" value="soal">
        <select name="game" onchange="this.form.submit()">
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= $soalFilter === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if (!$soalList): ?><p class="muted">Belum ada soal untuk game ini.</p><?php endif; ?>
      <table>
        <tr><th>#</th><th>Pertanyaan</th><th>Opsi</th><th>Kunci</th><th>Aksi</th></tr>
        <?php foreach ($soalList as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($s['pertanyaan']) ?><div class="muted"><?= e($s['penjelasan']) ?></div></td>
          <td>A. <?= e($s['opsi_a']) ?><br>B. <?= e($s['opsi_b']) ?><?= !empty($s['opsi_c']) ? '<br>C. ' . e($s['opsi_c']) : '' ?><?= !empty($s['opsi_d']) ? '<br>D. ' . e($s['opsi_d']) : '' ?></td>
          <td><b><?= e($s['kunci']) ?></b></td>
          <td class="inline">
            <a class="btn btn-secondary btn-sm" href="?tab=soal&edit=<?= (int) $s['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Hapus soal ini?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
              <input type="hidden" name="action" value="soal_delete">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn-danger btn-sm">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

  <!-- ================= TAB AI ================= -->
  <?php if ($tab === 'ai'): ?>
    <div class="panel">
      <h3>🤖 Buat Soal dengan AI (Google Gemini)</h3>
      <p class="muted">API key tersimpan aman di server (secrets.php). Soal yang dihasilkan bisa ditinjau dulu sebelum disimpan.</p>
      <?php if (!MPI_GEMINI_KEY): ?>
        <div class="alert err">API key Gemini belum diisi. Tambahkan <code>MPI_GEMINI_KEY</code> di <code>/var/www/mpi/secrets.php</code>.</div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
        <input type="hidden" name="action" value="ai_generate">
        <div class="inline">
          <div class="form-row" style="flex:1"><label>Game tujuan</label>
            <select name="game_id">
              <?php foreach ($games as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $aiGameId === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row" style="width:130px"><label>Jumlah soal</label><input type="number" name="jumlah" value="5" min="1" max="30"></div>
        </div>
        <div class="form-row"><label>Topik / perintah (contoh: "Berpikir komputasional, empat pilar, untuk kelas 9")</label><textarea name="topik" required placeholder="Tulis topik materi di sini..."></textarea></div>
        <button class="btn-primary">✨ Generate Soal</button>
      </form>
    </div>

    <?php if ($aiItems): ?>
    <div class="panel">
      <h3>Hasil Generate (<?= count($aiItems) ?> soal) — pilih lalu simpan</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(mpi_csrf()) ?>">
        <input type="hidden" name="action" value="ai_save">
        <input type="hidden" name="game_id" value="<?= $aiGameId ?>">
        <table>
          <tr><th>✔</th><th>Pertanyaan</th><th>Opsi</th><th>Kunci</th><th>Penjelasan</th></tr>
          <?php foreach ($aiItems as $i => $it): ?>
          <tr>
            <td><input type="checkbox" name="save[]" value="<?= $i ?>" checked></td>
            <td><?= e($it['pertanyaan']) ?></td>
            <td>
              <?php $pl = !empty($it['payload']) ? json_decode($it['payload'], true) : null; ?>
              <?php if (is_array($pl) && !empty($pl['kategori'])): ?>
                Kategori: <?= e($pl['kategori']) ?>
              <?php else: ?>
                A. <?= e($it['opsi_a']) ?><br>B. <?= e($it['opsi_b']) ?>
                <?php if (!empty($it['opsi_c'])): ?><br>C. <?= e($it['opsi_c']) ?><?php endif; ?>
                <?php if (!empty($it['opsi_d'])): ?><br>D. <?= e($it['opsi_d']) ?><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= (is_array($pl) && !empty($pl['kategori'])) ? '<span class="muted">—</span>' : '<b>' . e($it['kunci']) . '</b>' ?></td>
            <td class="muted"><?= e($it['penjelasan']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <div class="inline" style="margin-top:14px">
          <button class="btn-primary">💾 Simpan Soal Terpilih</button>
          <a class="btn btn-secondary" href="?tab=ai">Batal</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
<script>
(function () {
  function toggleSoalGroups() {
    var sel = document.getElementById('gameSelect');
    if (!sel) return;
    var tipe = sel.options[sel.selectedIndex].getAttribute('data-tipe') || 'benar_salah';
    document.querySelectorAll('.soal-group').forEach(function (g) {
      var show = (g.getAttribute('data-show') || '').split(',').indexOf(tipe) !== -1;
      g.hidden = !show;
      g.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !show; });
    });
  }
  document.addEventListener('DOMContentLoaded', toggleSoalGroups);
  var gs = document.getElementById('gameSelect');
  if (gs) gs.addEventListener('change', toggleSoalGroups);
})();
</script>
</body>
</html>
