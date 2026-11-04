<?php
// Importa a planilha de confirmações exportada pelo casar.com (.xlsx) para a tabela rsvps.
// Uma linha por convite (coluna "Convite/Responsável"); acompanhantes vão na mensagem.
// Só INSERE: e-mail que já tem confirmação fica como está (nada é sobrescrito nem apagado).
declare(strict_types=1);

/** Extrai um arquivo de dentro do .xlsx (ZIP) sem depender da extensão zip do PHP. */
function xlsx_arquivo(string $bin, string $nome): ?string
{
    $fim = strrpos($bin, "PK\x05\x06");
    if ($fim === false) {
        return null;
    }
    $eocd = unpack('vtotal/Vtam/Voff', substr($bin, $fim + 10, 10));
    $p = $eocd['off'];
    for ($i = 0; $i < $eocd['total']; $i++) {
        if (substr($bin, $p, 4) !== "PK\x01\x02") {
            return null;
        }
        $h = unpack('vmetodo/x4/Vcrc/Vcomp/Vtam/vnlen/vxlen/vclen/x8/Vlocal', substr($bin, $p + 10, 36));
        $arq = substr($bin, $p + 46, $h['nlen']);
        if ($arq === $nome) {
            $l = unpack('vnlen/vxlen', substr($bin, $h['local'] + 26, 4));
            $dados = substr($bin, $h['local'] + 30 + $l['nlen'] + $l['xlen'], $h['comp']);
            if ($h['metodo'] === 0) {
                return $dados;
            }
            return $h['metodo'] === 8 ? (gzinflate($dados) ?: null) : null;
        }
        $p += 46 + $h['nlen'] + $h['xlen'] + $h['clen'];
    }
    return null;
}

function xlsx_xml(string $bin, string $nome): ?SimpleXMLElement
{
    $x = xlsx_arquivo($bin, $nome);
    return $x === null ? null : (simplexml_load_string($x, SimpleXMLElement::class, LIBXML_NONET) ?: null);
}

/** Linhas da aba (por nome; sem ela, a primeira) como [ ['B' => 'texto', ...], ... ]. */
function xlsx_linhas(string $bin, string $aba): array
{
    $wb = xlsx_xml($bin, 'xl/workbook.xml');
    $rels = xlsx_xml($bin, 'xl/_rels/workbook.xml.rels');
    if (!$wb || !$rels) {
        throw new RuntimeException('Arquivo não parece uma planilha .xlsx.');
    }
    $alvos = [];
    foreach ($rels->Relationship as $r) {
        $alvos[(string) $r['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $r['Target']), '/');
    }
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $caminho = null;
    foreach ($wb->sheets->sheet as $s) {
        $alvo = $alvos[(string) $s->attributes($nsR)['id']] ?? null;
        if ($caminho === null || trim((string) $s['name']) === $aba) {
            $caminho = $alvo;
            if (trim((string) $s['name']) === $aba) {
                break;
            }
        }
    }
    $sh = $caminho ? xlsx_xml($bin, 'xl/' . $caminho) : null;
    if (!$sh) {
        throw new RuntimeException('Não achei a aba de confirmados na planilha.');
    }
    $textos = [];
    if ($ss = xlsx_xml($bin, 'xl/sharedStrings.xml')) {
        foreach ($ss->si as $si) {
            $t = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $n) {
                $t .= (string) $n;
            }
            $textos[] = $t;
        }
    }
    $linhas = [];
    foreach ($sh->sheetData->row as $row) {
        $l = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/\d+/', '', (string) $c['r']);
            $tipo = (string) $c['t'];
            $v = $tipo === 'inlineStr' ? (string) $c->is->t : (string) $c->v;
            if ($tipo === 's') {
                $v = $textos[(int) $v] ?? '';
            }
            $l[$col] = trim(preg_replace('/\s+/u', ' ', $v));
        }
        $linhas[] = $l;
    }
    return $linhas;
}

/** Agrupa as pessoas confirmadas por convite. */
function casar_convites(string $bin): array
{
    $linhas = xlsx_linhas($bin, 'Confirmados');
    $cols = null;
    $pessoas = [];
    foreach ($linhas as $l) {
        if ($cols === null) {
            $mapa = array_flip(array_map(fn($v) => mb_strtolower($v), $l));
            if (isset($mapa['nome'], $mapa['e-mail'])) {
                $cols = [
                    'resp' => $mapa['convite/responsável'] ?? $mapa['nome'], 'nome' => $mapa['nome'],
                    'tipo' => $mapa['adulto ou criança'] ?? null, 'tel' => $mapa['contato'] ?? null,
                    'email' => $mapa['e-mail'], 'data' => $mapa['data'] ?? null,
                ];
            }
            continue;
        }
        $nome = $l[$cols['nome']] ?? '';
        if ($nome === '') {
            continue;
        }
        $pessoas[] = [
            'resp' => ($l[$cols['resp']] ?? '') ?: $nome, 'nome' => $nome,
            'crianca' => $cols['tipo'] && mb_strtolower($l[$cols['tipo']] ?? '') === 'criança',
            'tel' => $cols['tel'] ? ($l[$cols['tel']] ?? '') : '',
            'email' => mb_strtolower($cols['email'] ? ($l[$cols['email']] ?? '') : ''),
            'data' => $cols['data'] ? ($l[$cols['data']] ?? '') : '',
        ];
    }
    if ($cols === null) {
        throw new RuntimeException('Não achei o cabeçalho (Nome, E-mail…) na aba de confirmados.');
    }
    $grupos = [];
    foreach ($pessoas as $p) {
        $grupos[$p['resp']][] = $p;
    }
    $convites = [];
    foreach ($grupos as $ps) {
        $tit = $ps[0];
        $email = preg_replace('/\.con$/', '.com', $tit['email']); // erro comum de digitação
        $acomp = [];
        foreach (array_slice($ps, 1) as $p) {
            $extra = [];
            if ($p['email'] !== '' && $p['email'] !== $tit['email']) $extra[] = $p['email'];
            if ($p['tel'] !== '' && $p['tel'] !== $tit['tel']) $extra[] = $p['tel'];
            $acomp[] = $p['nome'] . ($p['crianca'] ? ' (criança)' : '') . ($extra ? ' [' . implode(', ', $extra) . ']' : '');
        }
        $quando = casar_data($tit['data']);
        $msg = 'Confirmado pelo site antigo (casar.com)' . ($quando ? ' em ' . $quando->format('d/m/Y') : '') . '.';
        if ($acomp) {
            $msg .= ' Acompanhantes: ' . implode('; ', $acomp) . '.';
        }
        $convites[] = [
            'nome' => mb_substr($tit['nome'], 0, 160), 'email' => $email, 'telefone' => $tit['tel'] !== '' ? mb_substr($tit['tel'], 0, 30) : null,
            'adultos' => count(array_filter($ps, fn($p) => !$p['crianca'])), 'criancas' => count(array_filter($ps, fn($p) => $p['crianca'])),
            'mensagem' => mb_substr($msg, 0, 1000), 'criado_em' => ($quando ?? new DateTimeImmutable())->format('Y-m-d') . ' 12:00:00',
        ];
    }
    return $convites;
}

function casar_data(string $v): ?DateTimeImmutable
{
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
        return new DateTimeImmutable("$m[3]-$m[2]-$m[1]");
    }
    if (is_numeric($v) && (float) $v > 30000) { // data serial do Excel
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $v . ' days');
    }
    return null;
}

/** Grava os convites; devolve [inseridos, já existiam, ignorados (sem e-mail válido)]. */
function casar_importar(PDO $pdo, array $convites): array
{
    $ins = $pdo->prepare('INSERT IGNORE INTO rsvps (nome, email, telefone, vai, adultos, criancas, mensagem, token_edicao_hash, ip_hash, criado_em, atualizado_em) VALUES (?,?,?,1,?,?,?,?,?,?,?)');
    $novos = $existentes = $invalidos = 0;
    $pdo->beginTransaction();
    try {
        foreach ($convites as $c) {
            if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($c['nome']) < 2) {
                $invalidos++;
                continue;
            }
            $ins->execute([$c['nome'], $c['email'], $c['telefone'], $c['adultos'], $c['criancas'], $c['mensagem'],
                token_hash(token_novo()), ip_hash(), $c['criado_em'], $c['criado_em']]);
            $ins->rowCount() ? $novos++ : $existentes++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [$novos, $existentes, $invalidos];
}

/**
 * Pessoas de uma confirmação: titular (adulto) + acompanhantes. Os nomes dos acompanhantes só existem
 * nas confirmações importadas do casar.com (mensagem gerada por casar_convites); as do site novo têm só
 * a contagem, então completamos com "acompanhante sem nome" para a lista bater com os totais.
 */
function rsvp_pessoas(array $r): array
{
    $adultos = [(string) $r['nome']];
    $criancas = [];
    $msg = (string) ($r['mensagem'] ?? '');
    $pos = strpos($msg, 'Acompanhantes: ');
    if (str_starts_with($msg, 'Confirmado pelo site antigo (casar.com)') && $pos !== false) {
        $lista = preg_replace('/\.$/u', '', substr($msg, $pos + strlen('Acompanhantes: ')));
        foreach (explode('; ', $lista) as $item) {
            $item = trim(preg_replace('/\s*\[[^\]]*\]$/u', '', $item)); // tira [e-mail, telefone] extras
            if ($item === '') {
                continue;
            }
            if (preg_match('/^(.*) \(criança\)$/u', $item, $m)) {
                $criancas[] = $m[1];
            } else {
                $adultos[] = $item;
            }
        }
    }
    $pessoas = [];
    foreach (['adulto' => [$adultos, (int) $r['adultos']], 'criança' => [$criancas, (int) $r['criancas']]] as $tipo => [$nomes, $qtd]) {
        for ($i = 0; $i < $qtd; $i++) {
            $pessoas[] = [
                'nome' => $nomes[$i] ?? null, 'tipo' => $tipo, 'convite' => (string) $r['nome'],
                'titular' => $tipo === 'adulto' && $i === 0, 'data' => (string) $r['criado_em'],
            ];
        }
    }
    return $pessoas;
}

/** Chave para ordem alfabética sem depender da extensão intl (ignora acentos e maiúsculas). */
function chave_alfabetica(string $s): string
{
    return strtr(mb_strtolower(trim($s)), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
}
