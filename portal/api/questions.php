<?php
// File ini ada di subfolder api/, sedangkan config.php ada satu tingkat di atas
// (portal/config.php). Memakai __DIR__ . '/config.php' akan menunjuk ke
// portal/api/config.php yang tidak ada → fatal error → HTTP 500 pada SEMUA
// permintaan bank soal. Karena itu path-nya harus naik satu tingkat.
require dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

try {
    $slug = $_GET['game'] ?? '';
    if ($slug === '') {
        echo json_encode(['error' => 'Parameter ?game=slug diperlukan'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = mpi_db()->prepare('SELECT id, slug, nama, tipe FROM mpi_games WHERE slug = ? AND aktif = 1');
    $st->execute([$slug]);
    $game = $st->fetch();
    if (!$game) {
        http_response_code(404);
        echo json_encode(['error' => 'Game tidak ditemukan'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Urutan soal: default acak; ?order=asc untuk urutan level (mis. Millionaire)
    $order = (($_GET['order'] ?? '') === 'asc') ? 's.id ASC' : 'RAND()';
    $mapel = (int) ($_GET['mapel'] ?? 0);
    $sql = 'SELECT s.id, s.pertanyaan, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.kunci, s.penjelasan, s.payload, m.nama AS mapel_nama
            FROM mpi_soal s LEFT JOIN mpi_mapel m ON m.id = s.mapel_id
            WHERE s.game_id = ?';
    $params = [$game['id']];
    if ($mapel > 0) { $sql .= ' AND s.mapel_id = ?'; $params[] = $mapel; }
    $sql .= ' ORDER BY ' . $order;
    $st = mpi_db()->prepare($sql);
    $st->execute($params);
    $soal = $st->fetchAll();

    // Decode kolom JSON `payload` agar jadi objek/array, bukan string.
    foreach ($soal as &$s) {
        if (!empty($s['payload'])) {
            $decoded = json_decode($s['payload'], true);
            if (is_array($decoded)) { $s['payload'] = $decoded; }
        }
    }
    unset($s);

    // Ringkasan mata pelajaran (nama unik dari soal)
    $mapelNama = [];
    foreach ($soal as $s) {
        if (!empty($s['mapel_nama']) && !in_array($s['mapel_nama'], $mapelNama, true)) {
            $mapelNama[] = $s['mapel_nama'];
        }
    }

    echo json_encode(['game' => $game, 'mapel' => $mapelNama, 'soal' => $soal], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
}
