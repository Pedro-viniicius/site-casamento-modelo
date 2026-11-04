#!/usr/bin/env bash
# Gera versões WebP (480, 960 e 1600 px de largura) de cada foto em public_html/assets/img/fotos/
# e reduz o JPG original para no máximo 1600 px (qualidade 82, sem metadados/EXIF).
# Requer: ImageMagick (magick ou convert) e/ou cwebp. Uso: bash tools/otimizar-fotos.sh
set -euo pipefail
PASTA="$(cd "$(dirname "$0")/.." && pwd)/public_html/assets/img/fotos"
IM=""
command -v magick >/dev/null 2>&1 && IM="magick"
[ -z "$IM" ] && command -v convert >/dev/null 2>&1 && IM="convert"
TEM_CWEBP=0; command -v cwebp >/dev/null 2>&1 && TEM_CWEBP=1
if [ -z "$IM" ] && [ "$TEM_CWEBP" = 0 ]; then
  echo "Instale o ImageMagick (sudo apt install imagemagick) ou o cwebp (sudo apt install webp)." >&2
  exit 1
fi
shopt -s nullglob nocaseglob
n=0
for f in "$PASTA"/*.jpg "$PASTA"/*.jpeg "$PASTA"/*.png "$PASTA"/padrinhos/*.jpg "$PASTA"/padrinhos/*.jpeg; do
  base="${f%.*}"
  if [ -n "$IM" ]; then
    $IM "$f" -auto-orient -strip -resize '1600x1600>' -quality 82 -sampling-factor 4:2:0 -interlace JPEG "$f.tmp.jpg" && mv "$f.tmp.jpg" "${base}.jpg"
    [ "$f" != "${base}.jpg" ] && rm -f "$f"
  fi
  for w in 480 960 1600; do
    if [ "$TEM_CWEBP" = 1 ]; then
      cwebp -quiet -q 78 -metadata none -resize "$w" 0 "${base}.jpg" -o "${base}-${w}.webp"
    else
      $IM "${base}.jpg" -resize "${w}x>" -quality 78 -strip "${base}-${w}.webp"
    fi
  done
  n=$((n+1)); echo "ok  $(basename "${base}")"
done
echo "$n foto(s) otimizada(s). Suba a pasta assets/img/fotos/ inteira (JPG + WebP)."
