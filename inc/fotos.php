<?php
declare(strict_types=1);

function fotos_slots(): array
{
    static $s = null;
    return $s ??= require APP_RAIZ . '/config/fotos.php';
}

const FOTO_LARGURAS = [480, 960, 1600];

/**
 * Renderiza a foto do slot como <picture> (WebP 480/960/1600 se existirem + JPG),
 * ou um espaço reservado com ramo de oliveira quando o arquivo ainda não chegou.
 */
function foto(string $slot, string $sizes = '100vw', bool $lazy = true, string $classe = ''): string
{
    $s = fotos_slots()[$slot] ?? null;
    if (!$s) {
        return '';
    }
    return foto_arquivo('fotos/' . $s['arquivo'], (int) $s['w'], (int) $s['h'], $s['alt'], $s['legenda'], $sizes, $lazy, $classe);
}

function foto_arquivo(string $rel, int $w, int $h, string $alt, string $legenda, string $sizes = '100vw', bool $lazy = true, string $classe = ''): string
{
    $dir = PUBLIC_RAIZ . '/assets/img/';
    $arq = $dir . $rel;
    $cls = trim('foto ' . $classe);
    if (!is_file($arq)) {
        return '<div class="' . e($cls) . ' foto-slot" style="aspect-ratio:' . $w . '/' . $h . '" role="img" aria-label="' . e($alt) . ' (foto em breve)">'
            . '<svg class="foto-slot__ramo" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>'
            . '<span class="foto-slot__legenda">' . e($legenda) . '</span></div>';
    }
    $base = preg_replace('/\.(jpe?g|png)$/i', '', $rel);
    $srcset = [];
    foreach (FOTO_LARGURAS as $lg) {
        if (is_file($dir . $base . '-' . $lg . '.webp')) {
            $srcset[] = asset('assets/img/' . $base . '-' . $lg . '.webp') . ' ' . $lg . 'w';
        }
    }
    [$rw, $rh] = @getimagesize($arq) ?: [$w, $h];
    $img = '<img src="' . e(asset('assets/img/' . $rel)) . '" alt="' . e($alt) . '" width="' . (int) $rw . '" height="' . (int) $rh . '"'
        . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high" decoding="async"') . '>';
    $fonte = $srcset ? '<source type="image/webp" srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '">' : '';
    return '<picture class="' . e($cls) . '">' . $fonte . $img . '</picture>';
}

function foto_existe(string $slot): bool
{
    $s = fotos_slots()[$slot] ?? null;
    return $s && is_file(PUBLIC_RAIZ . '/assets/img/fotos/' . $s['arquivo']);
}
