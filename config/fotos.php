<?php
// Slots de fotos. Para trocar uma foto: salve o arquivo com o MESMO nome em
// public_html/assets/img/fotos/ e (opcional) rode tools/otimizar-fotos.sh para gerar as versões WebP.
// Enquanto o arquivo não existir, a página mostra um espaço reservado elegante (nunca imagem quebrada).
//
// w/h = tamanho de referência (define a proporção e evita "pulo" de layout).
// Troque os textos alternativos ('alt') por uma descrição de cada foto: é o que leitores de tela leem.
$galeria = [];
for ($i = 1; $i <= 16; $i++) {
    $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $galeria["galeria-$n"] = [
        'arquivo' => "galeria-$n.jpg", 'w' => 1200, 'h' => 1500,
        'alt' => "Foto $i do casal", 'legenda' => "galeria · foto $n", 'max_kb' => 300,
    ];
}

return [
    'hero' => [
        'arquivo' => 'hero.jpg', 'w' => 1200, 'h' => 1600,
        'alt' => 'Os noivos abraçados', 'legenda' => 'foto do casal · capa', 'max_kb' => 350,
    ],
    'noiva' => [
        'arquivo' => 'noiva.jpg', 'w' => 900, 'h' => 1200,
        'alt' => 'Retrato da noiva', 'legenda' => 'foto da noiva', 'max_kb' => 250,
    ],
    'noivo' => [
        'arquivo' => 'noivo.jpg', 'w' => 900, 'h' => 1200,
        'alt' => 'Retrato do noivo', 'legenda' => 'foto do noivo', 'max_kb' => 250,
    ],
    'cerimonia' => [
        'arquivo' => 'cerimonia.jpg', 'w' => 1600, 'h' => 1000,
        'alt' => 'Local da cerimônia', 'legenda' => 'local da cerimônia', 'max_kb' => 300,
    ],
    'recepcao' => [
        'arquivo' => 'recepcao.jpg', 'w' => 1600, 'h' => 1000,
        'alt' => 'Local da recepção', 'legenda' => 'local da recepção', 'max_kb' => 300,
    ],
] + $galeria;
// Padrinhos: o admin define o arquivo de cada um (pasta assets/img/fotos/padrinhos/, quadrado 600×600).
// og-image.jpg (1200×630) vai na mesma pasta e aparece na prévia do WhatsApp.
