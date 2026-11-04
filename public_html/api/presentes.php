<?php
// GET: estado atual da lista (para integrações e para conferência). Sem dados pessoais.
declare(strict_types=1);
require __DIR__ . '/../boot.php';

$lista = array_map(fn($p) => [
    'id' => $p['id'], 'slug' => $p['slug'], 'tipo' => $p['tipo'], 'nome' => $p['nome'], 'categoria' => $p['categoria'],
    'faixa' => $p['faixa'], 'preco' => $p['preco'], 'qtd' => $p['qtd'], 'marcados' => $p['marcados'], 'esgotado' => $p['esgotado'],
    'ofertas' => array_map(fn($o) => ['loja' => $o['loja_nome'], 'preco' => $o['preco'], 'href' => $o['href'], 'principal' => $o['principal'], 'verificado_em' => $o['verificado_em']], $p['ofertas']),
], presentes_lista());
header('Cache-Control: public, max-age=30');
json_out(['ok' => true, 'presentes' => $lista]);
