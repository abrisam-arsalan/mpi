<?php
require __DIR__ . '/config.php';
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
    $order = (($_GET['order'] ?? '') === 'asc') ? 'id ASC' : 'RAND()';
    $st = mpi_db()->prepare(
        'SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, penjelasan, payload
         FROM mpi_soal WHERE game_id = ? ORDER BY ' . $order
    );
    $st->execute([$game['id']]);
    $soal = $st->fetchAll();

    // Decode kolom JSON `payload` agar jadi objek/array, bukan string.
    foreach ($soal as &$s) {
        if (!empty($s['payload'])) {
            $decoded = json_decode($s['payload'], true);
            if (is_array($decoded)) { $s['payload'] = $decoded; }
        }
    }
    unset($s);

    echo json_encode(['game' => $game, 'soal' => $soal], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
}
