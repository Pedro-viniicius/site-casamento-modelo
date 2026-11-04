<?php
declare(strict_types=1);
require __DIR__ . '/../boot.php';
require APP_RAIZ . '/inc/admin.php';
require APP_RAIZ . '/inc/importar_casar.php';

admin_cabecalhos();
$pdo = db();
$tela = preg_replace('/[^a-z]/', '', (string) ($_GET['tela'] ?? 'presencas')) ?: 'presencas';
$admin = admin_atual();

/* ======================= AÇÕES (POST) ======================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $_POST;
    $acao = (string) ($in['acao'] ?? '');
    if (!csrf_ok($in['csrf'] ?? null)) {
        flash('Sessão expirada. Tente de novo.', false);
        redirecionar('/admin/?tela=' . $tela);
    }
    if ($acao === 'entrar') {
        if (admin_login(texto_limpo($in['usuario'] ?? '', 60), (string) ($in['senha'] ?? ''))) {
            redirecionar('/admin/');
        }
        flash('Usuário ou senha incorretos (ou muitas tentativas: espere 15 minutos).', false);
        redirecionar('/admin/');
    }
    if (!$admin || !$pdo) {
        redirecionar('/admin/');
    }
    if ($acao === 'sair') {
        admin_sair();
        redirecionar('/admin/');
    }

    $id = (int) ($in['id'] ?? 0);
    try {
        switch ($acao) {
            // ---- Presenças
            case 'rsvp_apagar':
                $pdo->prepare('DELETE FROM rsvps WHERE id = ?')->execute([$id]);
                flash('Confirmação apagada.');
                break;
            case 'rsvp_importar_casar':
                $up = $_FILES['planilha'] ?? null;
                if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || $up['size'] > 5 * 1024 * 1024) {
                    flash('Escolha o arquivo .xlsx exportado do casar.com (até 5 MB).', false);
                    break;
                }
                try {
                    $convites = casar_convites((string) file_get_contents($up['tmp_name']));
                } catch (RuntimeException $e) {
                    flash($e->getMessage(), false);
                    break;
                }
                [$novos, $existentes, $invalidos] = casar_importar($pdo, $convites);
                flash(sprintf('Planilha lida: %d convites. %d novos importados, %d já estavam no painel (mantidos como estão)%s.',
                    count($convites), $novos, $existentes, $invalidos ? ", $invalidos sem e-mail válido (ignorados)" : ''));
                break;

            // ---- Recados
            case 'recado_aprovar':
            case 'recado_ocultar':
                $pdo->prepare('UPDATE recados SET aprovado = ? WHERE id = ?')->execute([$acao === 'recado_aprovar' ? 1 : 0, $id]);
                flash($acao === 'recado_aprovar' ? 'Recado aprovado: já aparece no varal.' : 'Recado ocultado.');
                break;
            case 'recado_apagar':
                $pdo->prepare('DELETE FROM recados WHERE id = ?')->execute([$id]);
                flash('Recado apagado.');
                break;

            // ---- Presentes
            case 'presente_salvar':
                $dados = [
                    texto_limpo($in['nome'] ?? '', 160), texto_limpo($in['descricao'] ?? '', 500) ?: null,
                    in_array($in['categoria'] ?? '', CATEGORIAS, true) ? $in['categoria'] : 'Casa & Conforto',
                    ($in['tipo'] ?? '') === 'cota' ? 'cota' : 'item',
                    max(0, (float) str_replace(',', '.', (string) ($in['preco_ref'] ?? 0))),
                    max(1, min(500, (int) ($in['qtd_desejada'] ?? 1))),
                    !empty($in['prioridade']) ? 1 : 0, !empty($in['ativo']) ? 1 : 0, (int) ($in['ordem'] ?? 0),
                    imagem_externa_ok(trim((string) ($in['imagem_url'] ?? ''))),
                    texto_limpo($in['busca_etiqueta'] ?? '', 200) ?: null,
                ];
                if ($dados[0] === '') {
                    flash('O presente precisa de um nome.', false);
                    break;
                }
                if ($id) {
                    $pdo->prepare('UPDATE presentes SET nome=?, descricao=?, categoria=?, tipo=?, preco_ref=?, qtd_desejada=?, prioridade=?, ativo=?, ordem=?, imagem_url=?, busca_etiqueta=? WHERE id=?')
                        ->execute([...$dados, $id]);
                } else {
                    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $dados[0]) ?: 'presente')), '-') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                    $pdo->prepare('INSERT INTO presentes (nome, descricao, categoria, tipo, preco_ref, qtd_desejada, prioridade, ativo, ordem, imagem_url, busca_etiqueta, slug, faixa) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([...$dados, $slug, faixa_de($dados[4])]);
                    $id = (int) $pdo->lastInsertId();
                }
                atualizar_faixa($pdo, $id);
                flash('Presente salvo.');
                redirecionar('/admin/?tela=presente&id=' . $id);
            case 'presente_resetar':
                $pdo->prepare('UPDATE presentes_marcados SET desfeito_em = NOW() WHERE presente_id = ? AND desfeito_em IS NULL')->execute([$id]);
                flash('Marcações zeradas: o presente voltou a ficar disponível.');
                redirecionar('/admin/?tela=presente&id=' . $id);
            case 'presente_apagar':
                $pdo->prepare('DELETE FROM presentes WHERE id = ?')->execute([$id]);
                flash('Presente apagado.');
                redirecionar('/admin/?tela=presentes');

            // ---- Ofertas
            case 'oferta_salvar':
                $pid = (int) ($in['presente_id'] ?? 0);
                $url = trim((string) ($in['url'] ?? ''));
                $af = trim((string) ($in['link_afiliado'] ?? ''));
                if (!preg_match('#^https://#', $url) || ($af !== '' && !preg_match('#^https://#', $af))) {
                    flash('Use links completos começando com https://', false);
                    redirecionar('/admin/?tela=presente&id=' . $pid);
                }
                $vals = [
                    preg_replace('/[^a-z]/', '', strtolower((string) ($in['loja'] ?? ''))) ?: 'outra',
                    texto_limpo($in['titulo'] ?? '', 300), max(0, (float) str_replace(',', '.', (string) ($in['preco'] ?? 0))),
                    $url, $af ?: null, imagem_externa_ok(trim((string) ($in['imagem_url'] ?? ''))),
                    ($in['avaliacao'] ?? '') !== '' ? max(0, min(5, (float) str_replace(',', '.', (string) $in['avaliacao']))) : null,
                    !empty($in['principal']) ? 1 : 0,
                ];
                if (!empty($in['principal'])) {
                    $pdo->prepare('UPDATE ofertas SET principal = 0 WHERE presente_id = ?')->execute([$pid]);
                }
                if ($id) {
                    $pdo->prepare('UPDATE ofertas SET loja=?, titulo=?, preco=?, url=?, link_afiliado=?, imagem_url=?, avaliacao=?, principal=?, verificado_em=NOW() WHERE id=? AND presente_id=?')
                        ->execute([...$vals, $id, $pid]);
                } else {
                    $pdo->prepare("INSERT INTO ofertas (loja, titulo, preco, url, link_afiliado, imagem_url, avaliacao, principal, presente_id, origem) VALUES (?,?,?,?,?,?,?,?,?,'manual')")
                        ->execute([...$vals, $pid]);
                }
                atualizar_faixa($pdo, $pid);
                flash('Oferta salva (verificada agora).');
                redirecionar('/admin/?tela=presente&id=' . $pid);
            case 'oferta_verificada':
                $pid = (int) ($in['presente_id'] ?? 0);
                $preco = trim((string) ($in['preco'] ?? ''));
                if ($preco !== '') {
                    $pdo->prepare('UPDATE ofertas SET preco = ?, verificado_em = NOW() WHERE id = ? AND presente_id = ?')->execute([max(0, (float) str_replace(',', '.', $preco)), $id, $pid]);
                } else {
                    $pdo->prepare('UPDATE ofertas SET verificado_em = NOW() WHERE id = ? AND presente_id = ?')->execute([$id, $pid]);
                }
                atualizar_faixa($pdo, $pid);
                flash('Preço conferido.');
                redirecionar('/admin/?tela=presente&id=' . $pid);
            case 'oferta_apagar':
                $pid = (int) ($in['presente_id'] ?? 0);
                $pdo->prepare('DELETE FROM ofertas WHERE id = ? AND presente_id = ?')->execute([$id, $pid]);
                atualizar_faixa($pdo, $pid);
                flash('Oferta removida.');
                redirecionar('/admin/?tela=presente&id=' . $pid);

            // ---- Padrinhos
            case 'padrinho_salvar':
                $vals = [texto_limpo($in['nome'] ?? '', 160), ($in['lado'] ?? '') === 'noivo' ? 'noivo' : 'noiva', basename(texto_limpo($in['foto'] ?? '', 120)) ?: null, (int) ($in['ordem'] ?? 0)];
                if ($vals[0] === '') { flash('Informe o nome.', false); break; }
                if ($id) {
                    $pdo->prepare('UPDATE padrinhos SET nome=?, lado=?, foto=?, ordem=? WHERE id=?')->execute([...$vals, $id]);
                } else {
                    $pdo->prepare('INSERT INTO padrinhos (nome, lado, foto, ordem) VALUES (?,?,?,?)')->execute($vals);
                }
                flash('Padrinho salvo.');
                break;
            case 'padrinho_apagar':
                $pdo->prepare('DELETE FROM padrinhos WHERE id = ?')->execute([$id]);
                flash('Padrinho removido.');
                break;

            // ---- Configurações
            case 'config_salvar':
                $st = $pdo->prepare('INSERT INTO config_site (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
                foreach (array_keys(CONTEUDO_PADRAO) as $k) {
                    if (array_key_exists($k, $in['cfg'] ?? [])) {
                        $st->execute([$k, texto_limpo($in['cfg'][$k], 2000)]);
                    }
                }
                flash('Configurações salvas.');
                break;
        }
    } catch (Throwable $e) {
        error_log('[casamento] admin ' . $acao . ': ' . get_class($e));
        flash('Não deu certo salvar. Confira os campos e tente de novo.', false);
    }
    redirecionar('/admin/?tela=' . $tela);
}

function atualizar_faixa(PDO $pdo, int $pid): void
{
    $st = $pdo->prepare('SELECT COALESCE((SELECT preco FROM ofertas WHERE presente_id = p.id ORDER BY principal DESC, preco ASC LIMIT 1), p.preco_ref) FROM presentes p WHERE p.id = ?');
    $st->execute([$pid]);
    $preco = (float) $st->fetchColumn();
    $pdo->prepare('UPDATE presentes SET faixa = ? WHERE id = ?')->execute([faixa_de($preco), $pid]);
}

$csrf = csrf_token();
$f = flash();
$abas = ['presencas' => 'Presenças', 'presentes' => 'Presentes', 'recados' => 'Recados', 'padrinhos' => 'Padrinhos', 'config' => 'Configurações'];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php $cs = config_site(); [$ini1, $ini2] = iniciais_casal(); ?>
<title>Admin · <?= e($cs['noiva_nome'] . ' & ' . $cs['noivo_nome']) ?></title>
<link rel="icon" href="<?= e(asset('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<?php if (!$pdo): ?>
  <main class="admin__login card"><h1 class="subtitulo">Banco indisponível</h1><p>Confira <code>config/.env.php</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS) e se o <code>schema.sql</code> foi importado.</p></main>
<?php elseif (!$admin): ?>
  <main class="admin__login card">
    <p class="secao__titulo" style="font-size:2.6rem;text-align:center"><?= e($ini1) ?> &amp; <?= e($ini2) ?></p>
    <h1 class="subtitulo" style="text-align:center">Painel dos noivos</h1>
    <?php if ($f): ?><p class="faixa-aviso <?= $f['ok'] ? 'faixa-aviso--ok' : 'faixa-aviso--erro' ?>" role="alert"><?= e($f['msg']) ?></p><?php endif; ?>
    <form method="post" action="/admin/">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="entrar">
      <div class="campo"><label for="u">Usuário</label><input id="u" name="usuario" required autocomplete="username"></div>
      <div class="campo"><label for="s">Senha</label><input id="s" name="senha" type="password" required autocomplete="current-password"></div>
      <button class="btn btn--primario btn--bloco">Entrar</button>
    </form>
  </main>
<?php else: ?>
<header class="admin__topo">
  <a class="topo__marca" href="/" target="_blank" rel="noopener"><span class="topo__iniciais"><?= e($ini1) ?> <span class="e">&amp;</span> <?= e($ini2) ?></span> <span class="sr-only">abrir o site</span></a>
  <nav aria-label="Telas do admin"><ul class="admin__abas">
    <?php foreach ($abas as $k => $rot): $at = $tela === $k || ($tela === 'presente' && $k === 'presentes'); ?>
      <li><a href="/admin/?tela=<?= $k ?>" <?= $at ? 'aria-current="page"' : '' ?>><?= e($rot) ?></a></li>
    <?php endforeach; ?>
  </ul></nav>
  <form method="post" action="/admin/"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="sair"><button class="btn btn--texto btn--pequeno">Sair</button></form>
</header>
<main class="admin__main">
<?php if ($f): ?><p class="faixa-aviso <?= $f['ok'] ? 'faixa-aviso--ok' : 'faixa-aviso--erro' ?>" role="status"><?= e($f['msg']) ?></p><?php endif; ?>
<?php
switch ($tela) {
    /* ---------------------------- PRESENÇAS ---------------------------- */
    case 'presencas':
        $busca = texto_limpo($_GET['q'] ?? '', 100);
        // Ordenação por clique no cabeçalho da tabela: coluna => [rótulo, direção padrão no 1º clique].
        $colunas = ['nome' => ['Nome', 'asc'], 'vai' => ['Vai?', 'desc'], 'adultos' => ['Adultos', 'desc'], 'criancas' => ['Crianças', 'desc'],
            'email' => ['E-mail / telefone', 'asc'], 'atualizado' => ['Atualizado', 'desc']];
        [$ordem, $dir] = ordem_pedida($colunas, 'atualizado');
        $ver = in_array($_GET['ver'] ?? '', ['adultos', 'criancas', 'pessoas', 'nao'], true) ? $_GET['ver'] : '';
        $tot = $pdo->query('SELECT COALESCE(SUM(CASE WHEN vai=1 THEN adultos END),0) a, COALESCE(SUM(CASE WHEN vai=1 THEN criancas END),0) c, SUM(vai=1) sim, SUM(vai=0) nao, COUNT(*) n FROM rsvps')->fetch();
        // Só aspas simples em SQL: com ANSI_QUOTES (MySQL da Hostinger) "" vira nome de coluna e a tela dava 500.
        if ($busca === '') {
            $st = $pdo->query('SELECT * FROM rsvps');
        } else {
            $st = $pdo->prepare('SELECT * FROM rsvps WHERE nome LIKE ? OR email LIKE ?');
            $like = '%' . $busca . '%';
            $st->execute([$like, $like]);
        }
        $linhas = $st->fetchAll();
        // Ordena em PHP: texto sem acento/maiúscula (Úrsula fica no U); empate desempata pelo nome (A–Z).
        $linhas = ordenar_linhas($linhas, fn(array $r) => match ($ordem) {
            'nome' => chave_alfabetica($r['nome']), 'email' => chave_alfabetica($r['email']),
            'vai' => (int) $r['vai'], 'adultos' => (int) $r['adultos'], 'criancas' => (int) $r['criancas'],
            default => (string) $r['atualizado_em'],
        }, $dir, fn(array $r) => chave_alfabetica($r['nome']));
        // Link da própria tela mantendo busca/ordem/card aberto; $mudar troca ou remove (null) parâmetros.
        $url = fn(array $mudar) => '/admin/?' . http_build_query(array_filter(
            array_merge(['tela' => 'presencas', 'q' => $busca, 'ordem' => $ordem, 'dir' => $dir, 'ver' => $ver ?: null], $mudar),
            fn($v) => $v !== null && $v !== ''
        ));
        $cards = [
            'adultos'  => [(int) $tot['a'], 'adultos'],
            'criancas' => [(int) $tot['c'], 'crianças'],
            'pessoas'  => [(int) $tot['a'] + (int) $tot['c'], 'pessoas confirmadas'],
            'nao'      => [(int) $tot['nao'], 'não vão'],
        ];
        // Lista de "quem é" do card aberto (sempre de todas as confirmações, sem a busca), em ordem alfabética.
        $quem = [];
        if ($ver === 'nao') {
            foreach ($pdo->query('SELECT * FROM rsvps WHERE vai = 0') as $r) {
                $quem[] = ['nome' => $r['nome'], 'tipo' => '', 'convite' => $r['nome'], 'titular' => true, 'data' => $r['atualizado_em']];
            }
        } elseif ($ver !== '') {
            foreach ($pdo->query('SELECT * FROM rsvps WHERE vai = 1') as $r) {
                foreach (rsvp_pessoas($r) as $pp) {
                    if ($ver === 'pessoas' || ($ver === 'adultos' && $pp['tipo'] === 'adulto') || ($ver === 'criancas' && $pp['tipo'] === 'criança')) {
                        $quem[] = $pp;
                    }
                }
            }
        }
        // Com nome primeiro (A–Z); "sem nome" no fim, agrupados pelo convite.
        usort($quem, fn($x, $y) => [$x['nome'] === null, chave_alfabetica($x['nome'] ?? $x['convite'])] <=> [$y['nome'] === null, chave_alfabetica($y['nome'] ?? $y['convite'])]); ?>
        <h1 class="subtitulo">Presenças</h1>
        <div class="admin__totais">
          <?php foreach ($cards as $chave => [$n, $rotulo]): $aberto = $ver === $chave; ?>
          <a class="card admin__cartao<?= $aberto ? ' admin__cartao--aberto' : '' ?>" href="<?= e($url(['ver' => $aberto ? null : $chave]) . '#quem') ?>"<?= $aberto ? ' aria-current="true"' : '' ?> aria-label="<?= e(($aberto ? 'Fechar a lista de ' : 'Ver quem são: ') . $n . ' ' . $rotulo) ?>">
            <strong><?= $n ?></strong><span><?= e($rotulo) ?></span><small><?= $aberto ? 'fechar ✕' : 'ver quem ›' ?></small>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if ($ver !== ''): $semNome = count(array_filter($quem, fn($q) => $q['nome'] === null)); ?>
        <section class="card admin__quem" id="quem" aria-labelledby="quem-titulo">
          <div class="admin__quem-topo">
            <h2 class="subtitulo" id="quem-titulo"><?= e(ucfirst($cards[$ver][1])) ?> <span class="dica">(<?= count($quem) ?>, em ordem alfabética)</span></h2>
            <a class="btn btn--texto btn--pequeno" href="<?= e($url(['ver' => null])) ?>">Fechar ✕</a>
          </div>
          <?php if (!$quem): ?>
            <p class="vazio">Ninguém por aqui ainda.</p>
          <?php else: ?>
          <ol class="admin__quem-lista">
            <?php foreach ($quem as $q): ?>
            <li>
              <?php if ($q['nome'] !== null): ?><strong><?= e($q['nome']) ?></strong><?php else: ?><em>Acompanhante sem nome</em><?php endif; ?>
              <?php if ($ver === 'pessoas' && $q['tipo'] === 'criança'): ?> <span class="selo-mini">criança</span><?php endif; ?>
              <span class="dica"><?= $q['titular'] ? ($ver === 'nao' ? 'avisou que não vai' : 'responsável pelo convite') : 'convite de ' . e($q['convite']) ?></span>
            </li>
            <?php endforeach; ?>
          </ol>
          <?php if ($semNome): ?><p class="dica">“Acompanhante sem nome”: quem confirmou pelo site novo informa só quantos adultos e crianças vão, não os nomes.</p><?php endif; ?>
          <?php endif; ?>
        </section>
        <?php endif; ?>
        <form class="admin__barra" method="get"><input type="hidden" name="tela" value="presencas">
          <label class="sr-only" for="q">Buscar</label><input id="q" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome ou e-mail">
          <input type="hidden" name="ordem" value="<?= e($ordem) ?>"><input type="hidden" name="dir" value="<?= e($dir) ?>">
          <button class="btn btn--secundario btn--pequeno">Buscar</button>
          <a class="btn btn--primario btn--pequeno" href="/admin/exportar.php?tipo=presencas">Exportar CSV</a>
        </form>
        <details class="admin__importar">
          <summary class="btn btn--texto btn--pequeno">Importar planilha do casar.com</summary>
          <form class="admin__barra" method="post" action="/admin/?tela=presencas" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="rsvp_importar_casar">
            <label class="sr-only" for="planilha">Planilha .xlsx do casar.com</label>
            <input id="planilha" name="planilha" type="file" accept=".xlsx" required>
            <button class="btn btn--secundario btn--pequeno">Importar</button>
          </form>
          <p class="dica">Use a planilha exportada em casar.com → Confirmação de presença. Pode enviar de novo quando chegarem novas respostas: só entra quem ainda não está aqui (pelo e-mail), nada é apagado ou sobrescrito.</p>
        </details>
        <div class="tabela-rolagem"><table class="tabela">
          <thead><tr>
            <?php foreach ($colunas as $col => [$rotulo, $padrao]):
                if ($col === 'atualizado'): ?><th>Mensagem</th><?php endif; ?>
            <?= th_ordenavel($col, $rotulo, $padrao, $ordem, $dir, $url) ?>
            <?php endforeach; ?>
            <th><span class="sr-only">Ações</span></th>
          </tr></thead>
          <tbody>
          <?php foreach ($linhas as $r): ?>
            <tr><td><?= e($r['nome']) ?></td><td><?= $r['vai'] ? '✅ sim' : '— não' ?></td><td><?= (int) $r['adultos'] ?></td><td><?= (int) $r['criancas'] ?></td>
              <td><?= e($r['email']) ?><br><small><?= e($r['telefone']) ?></small></td><td class="tabela__msg"><?= e($r['mensagem']) ?></td>
              <td><small><?= e(date('d/m H:i', strtotime($r['atualizado_em']))) ?></small></td>
              <td><form method="post" data-confirmar="Apagar a confirmação de <?= e($r['nome']) ?>?"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="rsvp_apagar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn--texto btn--pequeno">Apagar</button></form></td></tr>
          <?php endforeach; if (!$linhas): ?><tr><td colspan="8" class="vazio">Nenhuma confirmação ainda.</td></tr><?php endif; ?>
          </tbody></table></div>
        <?php break;

    /* ---------------------------- PRESENTES ---------------------------- */
    case 'presentes':
        $lista = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM presentes_marcados m WHERE m.presente_id=p.id AND m.desfeito_em IS NULL) marcados, (SELECT COUNT(*) FROM ofertas o WHERE o.presente_id=p.id) n_ofertas, (SELECT MIN(verificado_em) FROM ofertas o WHERE o.presente_id=p.id) verif FROM presentes p ORDER BY p.tipo, p.ordem, p.id')->fetchAll();
        // Clique no cabeçalho ordena; sem coluna escolhida fica a ordem original (tipo, campo "Ordem").
        $colunas = ['nome' => ['Presente', 'asc'], 'categoria' => ['Categoria', 'asc'], 'ref' => ['Ref.', 'asc'], 'ofertas' => ['Ofertas', 'desc'],
            'marcados' => ['Marcados', 'desc'], 'verificado' => ['Verificado', 'desc'], 'ativo' => ['Ativo', 'desc']];
        [$ordem, $dir] = ordem_pedida($colunas);
        if ($ordem !== '') {
            $lista = ordenar_linhas($lista, fn(array $p) => match ($ordem) {
                'nome' => chave_alfabetica($p['nome']), 'categoria' => chave_alfabetica($p['categoria']),
                'ref' => (float) $p['preco_ref'], 'ofertas' => (int) $p['n_ofertas'],
                'marcados' => [(int) $p['marcados'], -(int) $p['qtd_desejada']],
                'verificado' => $p['verif'] ? (string) $p['verif'] : null, // sem verificação: sempre no fim
                default => (int) $p['ativo'],
            }, $dir, fn(array $p) => chave_alfabetica($p['nome']));
        }
        $url = fn(array $mudar) => '/admin/?' . http_build_query(array_filter(
            array_merge(['tela' => 'presentes', 'ordem' => $ordem ?: null, 'dir' => $dir ?: null], $mudar),
            fn($v) => $v !== null && $v !== ''
        )); ?>
        <h1 class="subtitulo">Presentes</h1>
        <div class="admin__barra">
          <a class="btn btn--primario btn--pequeno" href="/admin/?tela=presente&id=0">+ Novo presente ou cota</a>
          <a class="btn btn--secundario btn--pequeno" href="/admin/exportar.php?tipo=presentes">Exportar CSV (quem marcou)</a>
          <?php if ($ordem !== ''): ?><a class="btn btn--texto btn--pequeno" href="/admin/?tela=presentes">↺ Voltar à ordem original</a><?php endif; ?>
        </div>
        <p class="dica">Para trazer ofertas do Etiqueta, rode no seu computador: <code>node tools/importar-etiqueta.mjs --enviar <?= e(url_site()) ?></code></p>
        <div class="tabela-rolagem"><table class="tabela">
          <thead><tr><?php foreach ($colunas as $col => [$rotulo, $padrao]) echo th_ordenavel($col, $rotulo, $padrao, $ordem, $dir, $url); ?></tr></thead>
          <tbody>
          <?php foreach ($lista as $p): ?>
            <tr><td><a href="/admin/?tela=presente&id=<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></a><?= $p['prioridade'] ? ' ⭐' : '' ?></td><td><?= e($p['categoria']) ?></td><td><?= e(brl($p['preco_ref'])) ?></td>
              <td><?= (int) $p['n_ofertas'] ?: '<span class="pendente-tag">sem oferta</span>' ?></td><td><?= (int) $p['marcados'] ?>/<?= (int) $p['qtd_desejada'] ?></td>
              <td><?= $p['verif'] ? e(date('d/m', strtotime($p['verif']))) : '—' ?></td><td><?= $p['ativo'] ? 'sim' : 'não' ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php break;

    case 'presente':
        $id = (int) ($_GET['id'] ?? 0);
        $p = $id ? $pdo->prepare('SELECT * FROM presentes WHERE id = ?') : null;
        if ($p) { $p->execute([$id]); $p = $p->fetch(); }
        $p = $p ?: ['id' => 0, 'nome' => '', 'descricao' => '', 'categoria' => 'Cozinha', 'tipo' => 'item', 'preco_ref' => '', 'qtd_desejada' => 1, 'prioridade' => 0, 'ativo' => 1, 'ordem' => 500, 'imagem_url' => '', 'busca_etiqueta' => ''];
        $ofertas = [];
        $marcas = [];
        if ($p['id']) {
            $o = $pdo->prepare('SELECT * FROM ofertas WHERE presente_id = ? ORDER BY principal DESC, preco');
            $o->execute([$p['id']]);
            $ofertas = $o->fetchAll();
            $m = $pdo->prepare('SELECT nome_opcional, criado_em, desfeito_em FROM presentes_marcados WHERE presente_id = ? ORDER BY criado_em DESC LIMIT 30');
            $m->execute([$p['id']]);
            $marcas = $m->fetchAll();
        } ?>
        <p><a href="/admin/?tela=presentes">← Presentes</a></p>
        <h1 class="subtitulo"><?= $p['id'] ? e($p['nome']) : 'Novo presente' ?></h1>
        <form method="post" class="card admin__form">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="presente_salvar"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <div class="grade-2">
            <div class="campo"><label for="pn">Nome</label><input id="pn" name="nome" required maxlength="160" value="<?= e($p['nome']) ?>"></div>
            <div class="campo"><label for="pt">Tipo</label><select id="pt" name="tipo"><option value="item" <?= $p['tipo'] === 'item' ? 'selected' : '' ?>>Item de loja</option><option value="cota" <?= $p['tipo'] === 'cota' ? 'selected' : '' ?>>Cota (PIX)</option></select></div>
            <div class="campo"><label for="pc">Categoria</label><select id="pc" name="categoria"><?php foreach (CATEGORIAS as $cat): ?><option <?= $p['categoria'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="pr">Valor de referência / sugerido (R$)</label><input id="pr" name="preco_ref" inputmode="decimal" value="<?= e((string) $p['preco_ref']) ?>"></div>
            <div class="campo"><label for="pq">Quantidade desejada</label><input id="pq" name="qtd_desejada" type="number" min="1" max="500" value="<?= (int) $p['qtd_desejada'] ?>"></div>
            <div class="campo"><label for="po">Ordem</label><input id="po" name="ordem" type="number" value="<?= (int) $p['ordem'] ?>"></div>
          </div>
          <div class="campo"><label for="pd">Descrição (cotas)</label><input id="pd" name="descricao" maxlength="500" value="<?= e($p['descricao']) ?>"></div>
          <div class="campo"><label for="pi">Imagem (URL https do CDN da loja, opcional)</label><input id="pi" name="imagem_url" value="<?= e($p['imagem_url']) ?>"></div>
          <div class="campo"><label for="pb">Termos de busca no Etiqueta</label><input id="pb" name="busca_etiqueta" value="<?= e($p['busca_etiqueta']) ?>"></div>
          <label class="check"><input type="checkbox" name="prioridade" value="1" <?= $p['prioridade'] ? 'checked' : '' ?>> Mais desejado ⭐</label>
          <label class="check"><input type="checkbox" name="ativo" value="1" <?= $p['ativo'] ? 'checked' : '' ?>> Ativo (aparece no site)</label>
          <div class="acoes-linha"><button class="btn btn--primario">Salvar presente</button></div>
        </form>
        <?php if ($p['id']): ?>
        <h2 class="subtitulo">Ofertas</h2>
        <?php foreach ($ofertas as $o): $href = $o['link_afiliado'] ?: $o['url']; ?>
          <div class="card admin__oferta">
            <p><span class="loja-tag loja--<?= e($o['loja']) ?>"><?= e(loja_nome($o['loja'])) ?></span> <strong><?= e(brl($o['preco'])) ?></strong>
              <?= $o['principal'] ? '<span class="selo selo--oferta">principal</span>' : '' ?> <span class="dica">· <?= e($o['origem']) ?> · verificado em <?= e(date('d/m/Y H:i', strtotime($o['verificado_em']))) ?></span></p>
            <p class="dica"><?= e($o['titulo']) ?></p>
            <div class="acoes-linha">
              <a class="btn btn--secundario btn--pequeno" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer" data-reverificar>Reverificar preço ↗</a>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="oferta_verificada"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="presente_id" value="<?= (int) $p['id'] ?>">
                <label class="sr-only" for="np<?= (int) $o['id'] ?>">Preço conferido</label><input id="np<?= (int) $o['id'] ?>" name="preco" inputmode="decimal" placeholder="novo preço (opcional)" size="12">
                <button class="btn btn--primario btn--pequeno">Marcar como conferido</button></form>
              <form method="post" class="inline" data-confirmar="Remover esta oferta?"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="oferta_apagar"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="presente_id" value="<?= (int) $p['id'] ?>"><button class="btn btn--texto btn--pequeno">Remover</button></form>
            </div>
            <details><summary>Editar oferta</summary><?php oferta_form($csrf, (int) $p['id'], $o); ?></details>
          </div>
        <?php endforeach; ?>
        <details class="card" <?= $ofertas ? '' : 'open' ?>><summary><strong>+ Adicionar oferta manual</strong></summary><?php oferta_form($csrf, (int) $p['id'], null); ?></details>

        <h2 class="subtitulo">Marcações (“Já comprei”)</h2>
        <ul class="admin__lista">
          <?php foreach ($marcas as $m): ?><li><?= e($m['nome_opcional'] ?: 'Anônimo') ?> · <?= e(date('d/m H:i', strtotime($m['criado_em']))) ?><?= $m['desfeito_em'] ? ' <span class="dica">(desfeito)</span>' : '' ?></li><?php endforeach; if (!$marcas): ?><li class="dica">Ninguém marcou ainda.</li><?php endif; ?>
        </ul>
        <div class="acoes-linha">
          <form method="post" data-confirmar="Zerar as marcações deste presente?"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="presente_resetar"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn--secundario btn--pequeno">Zerar marcações</button></form>
          <form method="post" data-confirmar="Apagar este presente e suas ofertas? Não dá para desfazer."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="presente_apagar"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn--texto btn--pequeno">Apagar presente</button></form>
        </div>
        <?php endif;
        break;

    /* ---------------------------- RECADOS ---------------------------- */
    case 'recados':
        $rs = $pdo->query('SELECT * FROM recados ORDER BY aprovado ASC, criado_em DESC')->fetchAll(); ?>
        <h1 class="subtitulo">Recados</h1>
        <p class="dica">Novos recados ficam aguardando. Só os aprovados aparecem no varal do site.</p>
        <?php foreach ($rs as $r): ?>
          <div class="card admin__recado <?= $r['aprovado'] ? '' : 'admin__recado--novo' ?>">
            <p><strong><?= e($r['nome']) ?></strong> <span class="dica">· <?= e(date('d/m H:i', strtotime($r['criado_em']))) ?> · <?= $r['aprovado'] ? 'aprovado' : 'aguardando' ?></span></p>
            <p><?= nl2br(e($r['mensagem'])) ?></p>
            <div class="acoes-linha">
              <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="acao" value="<?= $r['aprovado'] ? 'recado_ocultar' : 'recado_aprovar' ?>"><button class="btn <?= $r['aprovado'] ? 'btn--secundario' : 'btn--primario' ?> btn--pequeno"><?= $r['aprovado'] ? 'Ocultar' : 'Aprovar' ?></button></form>
              <form method="post" data-confirmar="Apagar este recado?"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="acao" value="recado_apagar"><button class="btn btn--texto btn--pequeno">Apagar</button></form>
            </div>
          </div>
        <?php endforeach; if (!$rs): ?><p class="vazio">Nenhum recado ainda.</p><?php endif;
        break;

    /* ---------------------------- PADRINHOS ---------------------------- */
    case 'padrinhos':
        $ps = $pdo->query('SELECT * FROM padrinhos ORDER BY lado, ordem, id')->fetchAll(); ?>
        <h1 class="subtitulo">Padrinhos</h1>
        <p class="dica">Fotos: envie para <code>assets/img/fotos/padrinhos/</code> (quadradas, 600×600) e informe só o nome do arquivo.</p>
        <?php foreach ($ps as $pd): ?>
          <form method="post" class="card admin__linha-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="padrinho_salvar"><input type="hidden" name="id" value="<?= (int) $pd['id'] ?>">
            <input name="nome" value="<?= e($pd['nome']) ?>" aria-label="Nome" required>
            <select name="lado" aria-label="Lado"><option value="noiva" <?= $pd['lado'] === 'noiva' ? 'selected' : '' ?>>da noiva</option><option value="noivo" <?= $pd['lado'] === 'noivo' ? 'selected' : '' ?>>do noivo</option></select>
            <input name="foto" value="<?= e($pd['foto']) ?>" placeholder="arquivo.jpg" aria-label="Foto">
            <input name="ordem" type="number" value="<?= (int) $pd['ordem'] ?>" aria-label="Ordem" style="width:5em">
            <button class="btn btn--primario btn--pequeno">Salvar</button>
            <button class="btn btn--texto btn--pequeno" name="acao" value="padrinho_apagar" data-confirmar-botao="Remover <?= e($pd['nome']) ?>?">Remover</button>
          </form>
        <?php endforeach; ?>
        <form method="post" class="card admin__linha-form">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="padrinho_salvar">
          <input name="nome" placeholder="Nome do padrinho/madrinha" aria-label="Nome" required>
          <select name="lado" aria-label="Lado"><option value="noiva">da noiva</option><option value="noivo">do noivo</option></select>
          <input name="foto" placeholder="arquivo.jpg (opcional)" aria-label="Foto">
          <input name="ordem" type="number" value="<?= count($ps) * 10 + 10 ?>" aria-label="Ordem" style="width:5em">
          <button class="btn btn--primario btn--pequeno">+ Adicionar</button>
        </form>
        <?php break;

    /* ---------------------------- CONFIGURAÇÕES ---------------------------- */
    case 'config':
        $cfgAtual = config_site();
        $rotulos = [
            'noiva_nome' => 'Nome da noiva (site)', 'noiva_completo' => 'Nome completo da noiva', 'noivo_nome' => 'Nome do noivo (site)', 'noivo_completo' => 'Nome completo do noivo',
            'data_evento' => 'Data e hora (AAAA-MM-DD HH:MM)', 'local_nome' => 'Local', 'local_endereco' => 'Endereço', 'local_cidade' => 'Cidade (ex.: Campinas (SP))', 'local_lat' => 'Latitude', 'local_lng' => 'Longitude', 'local_place_id' => 'Google place_id',
            'recepcao_horario' => 'Recepção: horário', 'recepcao_local' => 'Recepção: local', 'estacionamento' => 'Estacionamento', 'boas_vindas' => 'Frase de boas-vindas (hero)',
            'frase_presentes' => 'Frase da lista de presentes', 'versiculo' => 'Versículo', 'versiculo_ref' => 'Referência do versículo',
            'historia' => 'Nossa história: uma linha por marco, no formato quando | título | texto (o “quando” pode ficar vazio)',
            'rsvp_prazo' => 'Prazo do RSVP (AAAA-MM-DD)', 'max_adultos' => 'Máx. adultos por confirmação', 'max_criancas' => 'Máx. crianças por confirmação',
            'pix_chave' => 'Chave PIX', 'pix_nome' => 'Nome do recebedor PIX (sem acento, até 25)', 'pix_cidade' => 'Cidade do recebedor PIX',
            'loja_fisica' => 'Loja física (texto)', 'loja_fisica_url' => 'Loja física (link)', 'abertura_animada' => 'Abertura em envelope (1 = ligada, 0 = desligada)',
            'whatsapp_texto' => 'Texto do compartilhar no WhatsApp',
        ]; ?>
        <h1 class="subtitulo">Configurações</h1>
        <p class="dica">Campos com <span class="pendente-tag">pendente</span> ficam escondidos dos convidados até serem preenchidos.</p>
        <form method="post" class="card admin__form">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="config_salvar">
          <?php foreach (CONTEUDO_PADRAO as $k => $_): $v = (string) ($cfgAtual[$k] ?? ''); ?>
            <div class="campo"><label for="c-<?= e($k) ?>"><?= e($rotulos[$k] ?? $k) ?> <?= marcar_pendente($v) ?></label>
              <?php if (mb_strlen($v) > 80 || $k === 'historia'): ?><textarea id="c-<?= e($k) ?>" name="cfg[<?= e($k) ?>]" rows="<?= $k === 'historia' ? 8 : 3 ?>"><?= e($v) ?></textarea>
              <?php else: ?><input id="c-<?= e($k) ?>" name="cfg[<?= e($k) ?>]" value="<?= e($v) ?>"><?php endif; ?></div>
          <?php endforeach; ?>
          <button class="btn btn--primario">Salvar configurações</button>
        </form>
        <?php break;
}
?>
</main>
<?php endif; ?>
</body>
</html>
<?php
function oferta_form(string $csrf, int $pid, ?array $o): void
{ ?>
  <form method="post" class="admin__form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="oferta_salvar">
    <input type="hidden" name="presente_id" value="<?= $pid ?>"><input type="hidden" name="id" value="<?= (int) ($o['id'] ?? 0) ?>">
    <div class="grade-2">
      <div class="campo"><label>Loja <select name="loja"><?php foreach (LOJAS as $k => $l): if ($k === 'mercadolivre') continue; ?><option value="<?= e($k) ?>" <?= ($o['loja'] ?? '') === $k ? 'selected' : '' ?>><?= e($l['nome']) ?></option><?php endforeach; ?></select></label></div>
      <div class="campo"><label>Preço (R$) <input name="preco" inputmode="decimal" required value="<?= e((string) ($o['preco'] ?? '')) ?>"></label></div>
    </div>
    <div class="campo"><label>Título <input name="titulo" required maxlength="300" value="<?= e($o['titulo'] ?? '') ?>"></label></div>
    <div class="campo"><label>URL limpa (https) <input name="url" required value="<?= e($o['url'] ?? '') ?>"></label></div>
    <div class="campo"><label>Link de afiliado (opcional, https) <input name="link_afiliado" value="<?= e($o['link_afiliado'] ?? '') ?>"></label></div>
    <div class="grade-2">
      <div class="campo"><label>Imagem (URL do CDN da loja) <input name="imagem_url" value="<?= e($o['imagem_url'] ?? '') ?>"></label></div>
      <div class="campo"><label>Avaliação (0 a 5) <input name="avaliacao" inputmode="decimal" value="<?= e((string) ($o['avaliacao'] ?? '')) ?>"></label></div>
    </div>
    <label class="check"><input type="checkbox" name="principal" value="1" <?= !empty($o['principal']) ? 'checked' : '' ?>> Oferta principal (botão do card)</label>
    <div class="acoes-linha"><button class="btn btn--primario btn--pequeno">Salvar oferta</button></div>
  </form>
<?php }
