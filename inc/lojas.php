<?php
declare(strict_types=1);

/** Lojas conhecidas: nome exibido no botão ("Ver na Amazon ↗") e a preposição certa. */
const LOJAS = [
    'amazon'       => ['nome' => 'Amazon',        'prep' => 'na'],
    'ml'           => ['nome' => 'Mercado Livre', 'prep' => 'no'],
    'mercadolivre' => ['nome' => 'Mercado Livre', 'prep' => 'no'],
    'magalu'       => ['nome' => 'Magalu',        'prep' => 'na'],
    'shopee'       => ['nome' => 'Shopee',        'prep' => 'na'],
    'casasbahia'   => ['nome' => 'Casas Bahia',   'prep' => 'nas'],
    'americanas'   => ['nome' => 'Americanas',    'prep' => 'nas'],
    'leroy'        => ['nome' => 'Leroy Merlin',  'prep' => 'na'],
    'camicado'     => ['nome' => 'Camicado',      'prep' => 'na'],
    'tokstok'      => ['nome' => 'Tok&Stok',      'prep' => 'na'],
];

function loja_nome(string $loja): string
{
    return LOJAS[$loja]['nome'] ?? ucfirst($loja);
}

function loja_rotulo(string $loja): string
{
    $l = LOJAS[$loja] ?? ['nome' => ucfirst($loja), 'prep' => 'na'];
    return 'Ver ' . $l['prep'] . ' ' . $l['nome'];
}
