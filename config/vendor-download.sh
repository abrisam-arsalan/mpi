#!/usr/bin/env bash
# ============================================================
# Unduh MediaPipe tasks-vision + model pose untuk SELF-HOST
# Jalankan di SERVER (yang punya akses internet):
#   sudo bash config/vendor-download.sh
# Hasil: /var/www/mpi/portal/game/vendor/
# ============================================================
set -euo pipefail

VERSION="0.10.3"
DIR="/var/www/mpi/portal/game/vendor"
WASM_DIR="$DIR/wasm"
CDN="https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${VERSION}"

mkdir -p "$WASM_DIR"

echo "▶ Mengunduh JS bundle..."
curl -fL -o "$DIR/vision_bundle.js" "$CDN/vision_bundle.mjs"

echo "▶ Mengunduh file WASM..."
for f in vision_wasm_internal.js vision_wasm_internal.wasm vision_wasm_nosimd_internal.js vision_wasm_nosimd_internal.wasm; do
  curl -fL -o "$WASM_DIR/$f" "$CDN/wasm/$f"
done

echo "▶ Mengunduh model pose landmarker lite..."
curl -fL -o "$DIR/pose_landmarker_lite.task" \
  "https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task"

echo "✅ Selesai. Isi folder vendor:"
ls -lh "$DIR" "$WASM_DIR"
