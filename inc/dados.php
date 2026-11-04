<?php
declare(strict_types=1);

/**
 * Conteúdo padrão (também está em database/seed_conteudo.sql).
 * Valores [[PENDENTE: ...]] ficam escondidos dos convidados e aparecem destacados no admin.
 */
const CONTEUDO_PADRAO = [
    'noiva_nome'        => 'Ana',
    'noiva_completo'    => 'Ana Exemplo',
    'noivo_nome'        => 'Lucas',
    'noivo_completo'    => 'Lucas Exemplo',
    'data_evento'       => '2027-06-12 16:30',
    'local_nome'        => 'Espaço Exemplo',
    'local_endereco'    => 'Rua Exemplo, 100, Centro, Cidade (UF), 00000-000',
    'local_cidade'      => 'Cidade (UF)',
    'local_lat'         => '-23.5505',
    'local_lng'         => '-46.6333',
    'local_place_id'    => '',
    'recepcao_horario'  => 'Logo após a cerimônia',
    'recepcao_local'    => 'No mesmo espaço da cerimônia',
    'estacionamento'    => '[[PENDENTE: estacionamento (tem? é gratuito? há manobrista?)]]',
    'boas_vindas'       => 'Criamos este cantinho para dividir com vocês os detalhes do nosso casamento. Estamos muito felizes e contamos com a sua presença no nosso grande dia!',
    'frase_presentes'   => 'O melhor presente é ter você com a gente.',
    'versiculo'         => 'O amor é paciente, o amor é bondoso.',
    'versiculo_ref'     => '1 Coríntios 13:4',
    'historia'          => "2020 | Como nos conhecemos | Conte aqui como vocês se conheceram.\n | Namoro | Vieram os sonhos, os aprendizados e os momentos especiais lado a lado.\n | Noivado | O “sim” que confirmou o que já vinha sendo escrito.\n12 jun 2027 | O grande dia | Um novo capítulo, celebrado ao lado das pessoas que amamos.",
    'rsvp_prazo'        => '[[PENDENTE: prazo para confirmar presença, ex.: 2027-05-20]]',
    'max_adultos'       => '6',
    'max_criancas'      => '4',
    'pix_chave'         => '[[PENDENTE: chave PIX]]',
    'pix_nome'          => '[[PENDENTE: nome de quem recebe o PIX]]',
    'pix_cidade'        => '[[PENDENTE: cidade de quem recebe o PIX]]',
    'loja_fisica'       => '[[PENDENTE: loja física com lista de presentes (opcional)]]',
    'loja_fisica_url'   => '',
    'abertura_animada'  => '1',
    'whatsapp_texto'    => 'Você é nosso convidado! 💍 Confirme sua presença e veja a lista de presentes:',
];

function config_site(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = CONTEUDO_PADRAO;
    if ($pdo = db()) {
        try {
            foreach ($pdo->query('SELECT chave, valor FROM config_site') as $l) {
                $c[$l['chave']] = $l['valor'];
            }
        } catch (Throwable $e) {
            error_log('[casamento] config_site: ' . get_class($e));
        }
    }
    return $c;
}

function conf(string $chave): string
{
    return (string) (config_site()[$chave] ?? '');
}

function data_evento(): DateTimeImmutable
{
    try {
        return new DateTimeImmutable(conf('data_evento'), new DateTimeZone('America/Sao_Paulo'));
    } catch (Throwable $e) {
        return new DateTimeImmutable('2027-06-12 16:30', new DateTimeZone('America/Sao_Paulo'));
    }
}

function faixa_de(float $preco): string
{
    return $preco <= 100 ? 'ate-100' : ($preco <= 250 ? '100-250' : 'acima-250');
}

const FAIXAS = [
    'ate-100'   => 'Até R$ 100',
    '100-250'   => 'R$ 100 a R$ 250',
    'acima-250' => 'Acima de R$ 250',
];

const CATEGORIAS = [
    'cozinha'          => 'Cozinha',
    'mesa-bar'         => 'Mesa & Bar',
    'cama-banho'       => 'Cama & Banho',
    'casa-conforto'    => 'Casa & Conforto',
    'eletroportateis'  => 'Eletroportáteis',
    'cotas'            => 'Cotas',
];

function categoria_slug(string $nome): string
{
    $s = array_search($nome, CATEGORIAS, true);
    return $s === false ? 'outros' : $s;
}

/** Normaliza uma oferta para a página (o botão usa o link de afiliado quando há). */
function oferta_publica(array $o): array
{
    $afiliado = !empty($o['link_afiliado']);
    return [
        'id'             => (int) ($o['id'] ?? 0),
        'loja'           => (string) $o['loja'],
        'loja_nome'      => loja_nome((string) $o['loja']),
        'titulo'         => (string) $o['titulo'],
        'preco'          => (float) $o['preco'],
        'href'           => $afiliado ? (string) $o['link_afiliado'] : (string) $o['url'],
        'afiliado'       => $afiliado,
        'imagem'         => imagem_externa_ok($o['imagem_url'] ?? null),
        'avaliacao'      => $o['avaliacao'] !== null ? (float) $o['avaliacao'] : null,
        'num_avaliacoes' => (int) ($o['num_avaliacoes'] ?? 0),
        'frete_gratis'   => (bool) ($o['frete_gratis'] ?? false),
        'principal'      => (bool) ($o['principal'] ?? false),
        'verificado_em'  => data_curta($o['verificado_em'] ?? null),
    ];
}

/** Só aceitamos URL https de imagem dos CDNs das lojas (nada é baixado). */
function imagem_externa_ok(?string $url): ?string
{
    if (!$url || !preg_match('#^https://(m\.media-amazon\.com|images-na\.ssl-images-amazon\.com|http2\.mlstatic\.com|down-br\.img\.susercontent\.com|a-static\.mlcdn\.com\.br|imgs\.casasbahia\.com\.br|static\.leroymerlin\.com\.br)/#', $url)) {
        return null;
    }
    return $url;
}

function montar_presente(array $p, array $ofertas, int $marcados): array
{
    usort($ofertas, fn($a, $b) => ($b['principal'] <=> $a['principal']) ?: ($a['preco'] <=> $b['preco']));
    $preco = $ofertas[0]['preco'] ?? (float) ($p['preco_ref'] ?? 0);
    $qtd = max(1, (int) ($p['qtd_desejada'] ?? 1));
    return [
        'id'          => (int) ($p['id'] ?? 0),
        'slug'        => (string) $p['slug'],
        'tipo'        => (string) ($p['tipo'] ?? 'item'),
        'nome'        => (string) $p['nome'],
        'descricao'   => (string) ($p['descricao'] ?? ''),
        'categoria'   => (string) $p['categoria'],
        'cat'         => categoria_slug((string) $p['categoria']),
        'preco_ref'   => (float) ($p['preco_ref'] ?? 0),
        'preco'       => (float) $preco,
        'faixa'       => faixa_de((float) $preco),
        'imagem'      => imagem_externa_ok($p['imagem_url'] ?? null) ?? ($ofertas[0]['imagem'] ?? null),
        'qtd'         => $qtd,
        'marcados'    => min($marcados, $qtd),
        'esgotado'    => $marcados >= $qtd,
        'prioridade'  => (bool) ($p['prioridade'] ?? false),
        'ordem'       => (int) ($p['ordem'] ?? 0),
        'ofertas'     => $ofertas,
    ];
}

/** Lista de presentes ativos: banco ou, sem banco, os JSON de data/. */
function presentes_lista(): array
{
    if ($pdo = db()) {
        try {
            $ps = $pdo->query('SELECT * FROM presentes WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();
            $ofs = $pdo->query('SELECT o.* FROM ofertas o JOIN presentes p ON p.id = o.presente_id WHERE p.ativo = 1')->fetchAll();
            $mk = $pdo->query('SELECT presente_id, COUNT(*) n FROM presentes_marcados WHERE desfeito_em IS NULL GROUP BY presente_id')->fetchAll(PDO::FETCH_KEY_PAIR);
            $porPresente = [];
            foreach ($ofs as $o) {
                $porPresente[$o['presente_id']][] = oferta_publica($o);
            }
            return array_map(fn($p) => montar_presente($p, $porPresente[$p['id']] ?? [], (int) ($mk[$p['id']] ?? 0)), $ps);
        } catch (Throwable $e) {
            error_log('[casamento] presentes: ' . get_class($e));
        }
    }
    return presentes_fallback();
}

function presentes_fallback(): array
{
    $base = json_decode((string) @file_get_contents(APP_RAIZ . '/data/presentes-base.json'), true) ?: ['presentes' => [], 'cotas' => []];
    $ofs = json_decode((string) @file_get_contents(APP_RAIZ . '/data/ofertas-etiqueta.json'), true) ?: ['presentes' => []];
    $porSlug = [];
    foreach ($ofs['presentes'] as $p) {
        $porSlug[$p['slug']] = array_map('oferta_publica', $p['ofertas']);
    }
    $lista = [];
    $ordem = 0;
    foreach ($base['presentes'] as $p) {
        $p['ordem'] = $ordem += 10;
        $lista[] = montar_presente($p, $porSlug[$p['slug']] ?? [], 0);
    }
    foreach ($base['cotas'] as $c) {
        $c += ['tipo' => 'cota', 'categoria' => 'Cotas', 'ordem' => $ordem += 10];
        $lista[] = montar_presente($c, [], 0);
    }
    return $lista;
}

// Exemplos mostrados só enquanto não há banco configurado.
const RECADOS_PADRAO = [
    ['nome' => 'Tia Rosa', 'mensagem' => 'Que Deus abençoe essa nova família. Felicidades sempre!'],
    ['nome' => 'Carlos e Júlia', 'mensagem' => 'Que esse dia fique marcado no coração de vocês para sempre. Parabéns!'],
];

/** Iniciais do casal para o monograma ("A & L"). */
function iniciais_casal(): array
{
    $c = config_site();
    return [mb_strtoupper(mb_substr(trim($c['noiva_nome']), 0, 1)), mb_strtoupper(mb_substr(trim($c['noivo_nome']), 0, 1))];
}

/** Linha do tempo da história (admin → Configurações → historia): uma linha por marco, "quando | título | texto". */
function historia_casal(): array
{
    $marcos = [];
    foreach (preg_split('/\R/u', conf('historia')) as $linha) {
        $partes = array_map('trim', explode('|', $linha, 3));
        if (count($partes) === 3 && $partes[1] !== '') {
            $marcos[] = $partes;
        }
    }
    return $marcos;
}

function recados_aprovados(): array
{
    if ($pdo = db()) {
        try {
            return $pdo->query('SELECT id, nome, mensagem FROM recados WHERE aprovado = 1 ORDER BY criado_em DESC, id DESC LIMIT 60')->fetchAll();
        } catch (Throwable $e) {
            error_log('[casamento] recados: ' . get_class($e));
        }
    }
    return RECADOS_PADRAO;
}

function padrinhos_lista(): array
{
    if ($pdo = db()) {
        try {
            return $pdo->query('SELECT id, nome, lado, foto FROM padrinhos ORDER BY lado, ordem, id')->fetchAll();
        } catch (Throwable $e) {
            error_log('[casamento] padrinhos: ' . get_class($e));
        }
    }
    return [];
}
