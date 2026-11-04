<?php
// POST: "Já comprei este presente" (marcar) e "Desfazer"/"Marquei por engano" (desfazer, pelo token do aparelho).
declare(strict_types=1);
require __DIR__ . '/../boot.php';

exigir_post();
$in = entrada();
if (!csrf_ok($in['csrf'] ?? null)) {
    responder(false, 'Sua sessão expirou. Recarregue a página, por favor.', [], 419, 'presentes');
}
$pdo = db_exigir();
$acao = ($in['acao'] ?? 'marcar') === 'desfazer' ? 'desfazer' : 'marcar';

function contagem(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT p.qtd_desejada, (SELECT COUNT(*) FROM presentes_marcados m WHERE m.presente_id = p.id AND m.desfeito_em IS NULL) AS n FROM presentes p WHERE p.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return ['marcados' => (int) ($r['n'] ?? 0), 'qtd' => (int) ($r['qtd_desejada'] ?? 1)];
}

if ($acao === 'desfazer') {
    if (!limite_ok('desfazer', 40, 3600)) {
        responder(false, 'Muitas tentativas. Espere um pouquinho, por favor.', [], 429, 'presentes');
    }
    $token = is_string($in['token'] ?? null) ? $in['token'] : '';
    $st = $pdo->prepare('SELECT id, presente_id FROM presentes_marcados WHERE token_hash = ? AND desfeito_em IS NULL');
    $st->execute([token_hash($token)]);
    $m = $st->fetch();
    if (!$m) {
        responder(false, 'Não achamos essa marcação (talvez já tenha sido desfeita).', [], 404, 'presentes');
    }
    $pdo->prepare('UPDATE presentes_marcados SET desfeito_em = NOW() WHERE id = ?')->execute([$m['id']]);
    responder(true, 'Desfeito.', contagem($pdo, (int) $m['presente_id']), 200, 'presentes');
}

if (!limite_ok('comprei', 15, 3600)) {
    responder(false, 'Muitas marcações seguidas. Espere alguns minutos, por favor.', [], 429, 'presentes');
}
$id = (int) ($in['presente_id'] ?? 0);
$nome = texto_limpo($in['nome'] ?? '', 120);

$pdo->beginTransaction();
$st = $pdo->prepare('SELECT id, nome, qtd_desejada FROM presentes WHERE id = ? AND ativo = 1 FOR UPDATE');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    $pdo->rollBack();
    responder(false, 'Esse presente não está mais na lista.', [], 404, 'presentes');
}
$n = contagem($pdo, $id)['marcados'];
if ($n >= (int) $p['qtd_desejada']) {
    $pdo->rollBack();
    responder(false, 'Alguém acabou de marcar este presente 💚 Que tal escolher outro?', contagem($pdo, $id), 409, 'presentes');
}
$token = token_novo();
$pdo->prepare('INSERT INTO presentes_marcados (presente_id, nome_opcional, token_hash, ip_hash) VALUES (?,?,?,?)')
    ->execute([$id, $nome ?: null, token_hash($token), ip_hash()]);
$pdo->commit();

responder(true, 'Obrigado! Marcamos “' . $p['nome'] . '” como presenteado 💚', ['token' => $token] + contagem($pdo, $id), 200, 'presentes');
