<?php
declare(strict_types=1);

const ICONE_CATEGORIA = [
    'cozinha' => '🍳', 'mesa-bar' => '🍷', 'cama-banho' => '🛏️',
    'casa-conforto' => '🛋️', 'eletroportateis' => '🔌', 'cotas' => '💝', 'outros' => '🎁',
];

function icone(string $id, string $classe = 'ic'): string
{
    return '<svg class="' . e($classe) . '" aria-hidden="true" focusable="false"><use href="#ic-' . e($id) . '"/></svg>';
}

/** Link externo: nova aba, ícone ↗ e aviso para leitor de tela. */
function link_externo(string $href, string $rotulo, string $classe = 'btn btn--primario', bool $patrocinado = false): string
{
    $rel = 'noopener' . ($patrocinado ? ' sponsored' : '') . ' noreferrer';
    return '<a class="' . e($classe) . '" href="' . e($href) . '" target="_blank" rel="' . $rel . '">'
        . e($rotulo) . ' <span aria-hidden="true" class="seta-externa">↗</span><span class="sr-only"> (abre em nova aba)</span></a>';
}

function nota_oferta(array $o): string
{
    $partes = [];
    if ($o['avaliacao']) {
        $partes[] = '★ ' . number_format($o['avaliacao'], 1, ',', '') . ($o['num_avaliacoes'] ? ' (' . number_format($o['num_avaliacoes'], 0, ',', '.') . ')' : '');
    }
    if ($o['frete_gratis']) {
        $partes[] = 'frete grátis';
    }
    return implode(' · ', $partes);
}

function card_presente(array $p, string $csrf, bool $pixOk): string
{
    $ofs = $p['ofertas'];
    $principal = $ofs[0] ?? null;
    $cota = $p['tipo'] === 'cota';
    $attrs = sprintf(
        'id="p-%s" data-id="%d" data-slug="%s" data-faixa="%s" data-cat="%s" data-preco="%s" data-nome="%s" data-desejado="%d" data-ordem="%d" data-qtd="%d" data-marcados="%d" data-tipo="%s"',
        e($p['slug']), $p['id'], e($p['slug']), e($p['faixa']), e($p['cat']), e((string) $p['preco']),
        e(mb_strtolower($p['nome'] . ' ' . $p['categoria'] . ' ' . ($principal['titulo'] ?? ''))),
        $p['prioridade'] ? 1 : 0, $p['ordem'], $p['qtd'], $p['marcados'], e($p['tipo'])
    );
    $h = '<article class="presente card carrossel__item' . ($p['esgotado'] ? ' presente--esgotado' : '') . '" ' . $attrs . '>';

    // Imagem (CDN da loja) ou ícone da categoria.
    $h .= '<div class="presente__img">';
    if ($p['imagem']) {
        $h .= '<img src="' . e($p['imagem']) . '" alt="" width="300" height="300" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
    } else {
        $h .= '<span class="presente__emoji" aria-hidden="true">' . (ICONE_CATEGORIA[$p['cat']] ?? '🎁') . '</span>';
    }
    $h .= '</div>';

    $h .= '<div class="presente__selos">';
    if ($p['prioridade']) {
        $h .= '<span class="selo selo--desejado">Mais desejado</span>';
    }
    if ($principal && count($ofs) > 1) {
        $h .= '<span class="selo selo--oferta">Melhor oferta</span>';
    }
    $h .= '<span class="selo selo--presenteado" ' . ($p['esgotado'] ? '' : 'hidden') . '>' . ($cota ? 'Cota completa 💚' : 'Já presenteado 💚') . '</span>';
    $h .= '</div>';

    $h .= '<h3 class="presente__nome">' . e($p['nome']) . '</h3>';
    if ($cota && $p['descricao']) {
        $h .= '<p class="presente__desc">' . e($p['descricao']) . '</p>';
    }

    // Preço + loja
    if ($cota) {
        $h .= '<p class="presente__preco"><span class="presente__rotulo">Valor sugerido</span> <strong>' . e(brl($p['preco'])) . '</strong></p>';
    } elseif ($principal) {
        $h .= '<p class="presente__preco"><strong>' . e(brl($principal['preco'])) . '</strong> <span class="loja-tag loja--' . e($principal['loja']) . '">' . e($principal['loja_nome']) . '</span></p>';
        $nota = nota_oferta($principal);
        if ($nota) {
            $h .= '<p class="presente__nota">' . e($nota) . '</p>';
        }
        $h .= '<p class="presente__verif">Preço verificado em ' . e($principal['verificado_em']) . '; pode variar na loja.</p>';
    } else {
        $h .= '<p class="presente__preco"><span class="presente__rotulo">Referência</span> <strong>' . e(brl($p['preco_ref'])) . '</strong></p>';
        $h .= '<p class="presente__verif">Estamos escolhendo a melhor oferta. Pode comprar onde preferir 💛</p>';
    }

    if ($p['qtd'] > 1) {
        $h .= '<p class="presente__progresso" data-progresso>' . (int) $p['marcados'] . ' de ' . (int) $p['qtd'] . ($cota ? ' cotas recebidas' : ' já presenteados') . '</p>';
    }

    // Ações
    $h .= '<div class="presente__acoes">';
    if ($cota) {
        $h .= $pixOk
            ? '<button type="button" class="btn btn--primario" data-pix data-valor="' . e((string) $p['preco']) . '" data-nome="' . e($p['nome']) . '">' . icone('pix') . ' Contribuir com PIX</button>'
            : '<button type="button" class="btn btn--primario" disabled>PIX disponível em breve</button>';
    } elseif ($principal) {
        $h .= link_externo($principal['href'], loja_rotulo($principal['loja']), 'btn btn--primario', $principal['afiliado']);
        if (count($ofs) > 1) {
            $h .= '<details class="presente__outras"><summary class="btn btn--texto">Outras lojas (' . (count($ofs) - 1) . ')</summary><ul class="lista-ofertas">';
            foreach (array_slice($ofs, 1) as $o) {
                $h .= '<li class="oferta-alt"><div><span class="loja-tag loja--' . e($o['loja']) . '">' . e($o['loja_nome']) . '</span> <strong>' . e(brl($o['preco'])) . '</strong>'
                    . '<p class="oferta-alt__titulo">' . e(mb_strimwidth($o['titulo'], 0, 90, '…')) . '</p>'
                    . (nota_oferta($o) !== '' ? '<p class="presente__nota">' . e(nota_oferta($o)) . '</p>' : '')
                    . '</div>' . link_externo($o['href'], loja_rotulo($o['loja']), 'btn btn--secundario btn--pequeno', $o['afiliado']) . '</li>';
            }
            $h .= '</ul></details>';
        }
    }
    if ($p['id'] > 0) {
        $rotulo = $cota ? 'Já contribuí' : 'Já comprei este presente';
        $h .= '<form class="presente__form" method="post" action="/api/comprei.php">'
            . '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="presente_id" value="' . (int) $p['id'] . '">'
            . '<button type="submit" class="btn btn--secundario" data-comprei ' . ($p['esgotado'] ? 'disabled' : '') . '>' . icone('presente') . ' ' . $rotulo . '</button>'
            . '</form>'
            . '<button type="button" class="btn btn--texto btn--pequeno" data-desmarcar hidden>Marquei por engano</button>';
    }
    $h .= '</div></article>';
    return $h;
}
